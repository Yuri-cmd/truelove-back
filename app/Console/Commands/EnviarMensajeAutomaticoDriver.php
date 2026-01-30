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

        // Pedidos creados en las últimas 2 horas (ajusta la condición según tu lógica)
        $pedidos = Pedido::whereBetween('created_at', [$desde, $hasta])->get();

        foreach ($pedidos as $pedido) {
            // Saltar si ya fue marcado
            if ($pedido->notified_arrival) {
                $this->info("Pedido #{$pedido->id} ya marcado como notificado. Saltando.");
                continue;
            }

            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if (! $pedidoTracking) continue;

            if ($pedidoTracking->estado == 7) {
                // Intentamos marcarlo atómicamente para evitar condiciones de carrera
                $updated = Pedido::where('id', $pedido->id)
                    ->where('notified_arrival', false)
                    ->update(['notified_arrival' => true]);

                if (! $updated) {
                    // Otra ejecución ya lo marcó
                    $this->info("Pedido #{$pedido->id} ya fue marcado por otra ejecución. Saltando.");
                    continue;
                }

                // A partir de aquí, el pedido está marcado como notificado (notified_arrival = 1).
                // Si algo falla antes/pendiente de enviar, revertimos la marca.
                $motorizado = RepartoRegistro::find($pedido->id_motorizado);
                $negocio = Establecimiento::where('business_registration_id', $pedido->id_local)->first();

                if (! $motorizado || ! $negocio) {
                    // Revertir para permitir intentos futuros
                    Pedido::where('id', $pedido->id)->update(['notified_arrival' => false]);
                    $this->error("Faltan datos para el pedido #{$pedido->id}. Revirtiendo marca.");
                    continue;
                }

                $nombre = $motorizado->nombres;
                $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();

                // Calcular total con formato correcto
                $sumaDetalles = $pedidoDetalles->sum(function ($detalle) {
                    return $detalle->precio * $detalle->cantidad;
                });
                $sumaDetalles = (float) $sumaDetalles; 
                $precioDelivery = (float) $pedido->precio_delivery;
                $descuento = (float) $pedido->descuento;
                $totalFloat = $sumaDetalles + $precioDelivery - $descuento;
                $total = number_format($totalFloat, 2, '.', '');

                $mensaje = "Hola soy {$nombre}, tu Driver de TRUE LOVE DELIVERY. Acabo de llegar con tu pedido de {$negocio->nombre_establecimiento}. El subtotal a pagar incluyendo el delivery sería S/{$total}.";

                $cliente = Cliente::find($pedido->id_cliente);

                try {
                    if ($cliente && $cliente->token_fmc) {
                        // Enviar notificación (puedes adaptar la llamada según tu servicio)
                        $this->firebaseService->sendNotification(
                            $cliente->token_fmc,
                            'Hola ' . ($cliente->nombre ?? ''),
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

                    $this->info("Mensaje enviado y pedido #{$pedido->id} marcado como notificado.");
                } catch (Exception $e) {
                    // Si falla el envío, revertimos la marca para reintentar en la próxima corrida
                    Pedido::where('id', $pedido->id)->update(['notified_arrival' => false]);
                    $this->error("Error al enviar notificación para pedido #{$pedido->id}: " . $e->getMessage());
                    continue;
                }
            }
        }
    }
}
