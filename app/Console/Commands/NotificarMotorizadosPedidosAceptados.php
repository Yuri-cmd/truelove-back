<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\PedidoTracking;
use App\Services\FirebaseService;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificarMotorizadosPedidosAceptados extends Command
{
    protected $signature = 'pedidos:notificar-motorizados {--minutos=4 : Minutos de espera tras aceptar el pedido}';
    protected $description = 'Avisa a los motorizados de los pedidos aceptados por el local hace X minutos y que siguen sin motorizado';

    public function handle(PedidoService $pedidoService, FirebaseService $firebaseService)
    {
        $minutos = (int) $this->option('minutos');
        $limite = Carbon::now()->subMinutes($minutos);
        $desde = Carbon::now()->subHours(2);

        // Aceptados (estado 2) dentro de la ventana [hace 2h, hace X min]
        $aceptados = PedidoTracking::where('estado', 2)
            ->whereBetween('created_at', [$desde, $limite])
            ->pluck('pedido_id');

        $pendientes = Pedido::whereIn('id', $aceptados)
            ->whereNull('id_motorizado')
            ->whereIn('tipo_pedido', [0, '0'])
            ->get()
            ->filter(function ($pedido) {
                // Solo si siguen activos: último estado 2 (aceptado) o 3 (listo)
                $ultimo = PedidoTracking::where('pedido_id', $pedido->id)->latest('id')->first();
                return $ultimo && in_array((int) $ultimo->estado, [2, 3], true);
            })
            ->filter(fn ($pedido) => Cache::add("pedido_notif_motorizados_{$pedido->id}", 1, now()->addHours(3)));

        if ($pendientes->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($pedidoService->obtenerPedidosCercanos() as $motorizado) {
            if (!$motorizado['token']) {
                continue;
            }
            try {
                $firebaseService->sendNotificationWithSound(
                    $motorizado['token'],
                    '🛵 Nuevo Pedido Disponible',
                    '📍 Un nuevo pedido está disponible. ¡No lo dejes pasar!',
                    'nuevo_pedido',
                    'pedidos_v7',
                    [],
                    'motorizado',
                    $motorizado['id'],
                    'motorizado'
                );
            } catch (\Throwable $e) {
                Log::warning("Error notificando motorizado {$motorizado['id']}: " . $e->getMessage());
            }
        }

        $this->info('Motorizados notificados por pedidos: ' . $pendientes->pluck('id')->implode(', '));
        return self::SUCCESS;
    }
}
