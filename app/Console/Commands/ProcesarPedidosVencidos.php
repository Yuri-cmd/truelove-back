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
use Exception;
use Illuminate\Console\Command;

class ProcesarPedidosVencidos extends Command
{
    protected $signature = 'pedidos:procesar-vencidos {--horas=24 : Horas hacia atrás para buscar pedidos}';
    protected $description = 'Procesa pedidos vencidos que no se notificaron automáticamente';

    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        parent::__construct();
        $this->firebaseService = $firebaseService;
    }

    public function handle()
    {
        $horas = $this->option('horas');
        $this->info("Buscando pedidos de las últimas {$horas} horas...");
        
        $desde = Carbon::now()->subHours($horas);
        $hasta = Carbon::now();

        // Buscar pedidos sin procesar en el rango de tiempo extendido
        $pedidos = Pedido::whereBetween('created_at', [$desde, $hasta])
                         ->where('notified_arrival', false)
                         ->get();

        $this->info("Encontrados " . count($pedidos) . " pedidos sin procesar.");

        $procesados = 0;
        $errores = 0;

        foreach ($pedidos as $pedido) {
            $this->info("Procesando pedido #{$pedido->id}...");
            
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if (!$pedidoTracking) {
                $this->warn("Pedido #{$pedido->id}: No tiene tracking");
                continue;
            }

            $this->info("Pedido #{$pedido->id}: Estado actual {$pedidoTracking->estado}");

            // Procesar pedidos en estado 7 (Llegó Motorizado) o superiores
            if ($pedidoTracking->estado >= 7) {
                try {
                    // Actualizar marca atómicamente
                    $updated = Pedido::where('id', $pedido->id)
                        ->where('notified_arrival', false)
                        ->update(['notified_arrival' => true]);

                    if (!$updated) {
                        $this->warn("Pedido #{$pedido->id}: Ya fue procesado por otro hilo");
                        continue;
                    }

                    // Obtener datos necesarios
                    $motorizado = RepartoRegistro::find($pedido->id_motorizado);
                    $negocio = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
                    $cliente = Cliente::find($pedido->id_cliente);

                    if (!$motorizado || !$negocio || !$cliente) {
                        $this->error("Pedido #{$pedido->id}: Faltan datos (motorizado, negocio o cliente)");
                        // Revertir marca
                        Pedido::where('id', $pedido->id)->update(['notified_arrival' => false]);
                        $errores++;
                        continue;
                    }

                    // Calcular total
                    $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                    $sumaDetalles = $pedidoDetalles->sum(function ($detalle) {
                        return $detalle->precio * $detalle->cantidad;
                    });
                    $totalFloat = (float)$sumaDetalles + (float)$pedido->precio_delivery - (float)$pedido->descuento;
                    $total = number_format($totalFloat, 2, '.', '');

                    // Crear mensaje
                    $mensaje = "Hola soy {$motorizado->nombres}, tu Driver de TRUE LOVE DELIVERY. Acabo de llegar con tu pedido de {$negocio->nombre_establecimiento}. El subtotal a pagar incluyendo el delivery sería S/{$total}.";

                    // Enviar notificación
                    if ($cliente->token_fmc) {
                        $this->firebaseService->sendNotification(
                            $cliente->token_fmc,
                            'Hola ' . ($cliente->nombre ?? ''),
                            $mensaje,
                            [],
                            'cliente',
                            $cliente->id,
                            'cliente'
                        );
                    }

                    // Guardar en chat
                    Chat::create([
                        'pedido_id' => $pedido->id,
                        'sender_id' => $pedido->id_motorizado,
                        'receiver_id' => $pedido->id_cliente,
                        'message' => $mensaje,
                    ]);

                    $this->info("✅ Pedido #{$pedido->id} procesado correctamente");
                    $procesados++;

                } catch (Exception $e) {
                    $this->error("❌ Error procesando pedido #{$pedido->id}: " . $e->getMessage());
                    // Revertir marca para reintentar después
                    Pedido::where('id', $pedido->id)->update(['notified_arrival' => false]);
                    $errores++;
                }
            } else {
                $this->info("Pedido #{$pedido->id}: Estado {$pedidoTracking->estado} no requiere procesamiento");
            }
        }

        $this->info("=== RESUMEN ===");
        $this->info("Pedidos procesados exitosamente: {$procesados}");
        $this->info("Errores: {$errores}");
        $this->info("Comando completado.");
    }
}