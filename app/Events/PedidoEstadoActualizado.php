<?php

namespace App\Events;

use App\Http\Controllers\PedidoTrackingController;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cada vez que se crea un PedidoTracking (ver
 * App\Models\PedidoTracking::booted()). Usa ShouldBroadcastNow en vez de
 * ShouldBroadcast porque QUEUE_CONNECTION=database en este proyecto — con
 * ShouldBroadcast el evento se encolaría y solo saldría cuando corra un
 * `queue:work`, lo que le quitaría el sentido a tener tiempo real.
 *
 * Canal público (no PrivateChannel): las rutas HTTP de tracking actuales
 * (routes/api_cliente.php) tampoco exigen autenticación — conocer el ID del
 * pedido ya es "suficiente" en este modelo de confianza. Este evento solo
 * iguala esa misma política, no la relaja.
 */
class PedidoEstadoActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $pedidoId;

    public function __construct(int $pedidoId)
    {
        $this->pedidoId = $pedidoId;
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('pedido.' . $this->pedidoId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'estado.actualizado';
    }

    public function broadcastWith(): array
    {
        return PedidoTrackingController::estadoPayload($this->pedidoId) ?? [];
    }
}
