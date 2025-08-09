<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarMensajeAutomaticoDriver extends Command
{
    protected $signature = 'mensaje:auto-driver';
    protected $description = 'Envia mensaje automatico del driver al cliente';

    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $desde = Carbon::now()->subHours(2);
        $hasta = Carbon::now();

        // Buscar pedidos entregados en las últimas 2 horas
        $pedidos = Pedido::whereBetween('created_at', [$desde, $hasta])->get();
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if ($pedidoTracking->estado == 7) {
                $motorizado = RepartoRegistro::find($pedido->id_motorizado);
                $negocio = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
                if (!$motorizado) continue;
                if (!$negocio) continue;

                $nombre = $motorizado->nombres;
                $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                $total = number_format($pedidoDetalles->sum('precio'), 2);
                $direccion = $pedido->direccion ?? 'la dirección que nos brindaste';

                $mensaje = "Hola soy {$nombre}, tú Driver de TRUE LOVE DELIVERY. Acabo de llegar con tu pedido de {$negocio->nombre_establecimiento}. El subtotal a pagar incluyendo el delivery sería S/{$total}.";

                $cliente = Cliente::where('id', $pedido->id_cliente)->first();
                if ($cliente->token_fmc) {
                    $this->firebaseService->sendNotification(
                        $cliente->token_fmc,
                        'Hola ' . $cliente->nombre,
                        $mensaje
                    );
                }
                
                // Guardar en la tabla chat
                Chat::create([
                    'pedido_id' => $pedido->id,
                    'sender_id' => $pedido->id_motorizado,
                    'receiver_id' => $pedido->id_cliente,
                    'message' => $mensaje,
                ]);

                $this->info("Mensaje enviado al pedido #{$pedido->id}");
            }
        }
    }
}
