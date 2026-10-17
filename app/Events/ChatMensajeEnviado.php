<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cada vez que se crea un Chat (ver App\Models\Chat::booted()).
 * ShouldBroadcastNow por la misma razón que PedidoEstadoActualizado: sin
 * queue:work corriendo, ShouldBroadcast nunca saldría en tiempo real.
 *
 * Canal público `chat.{pedidoId}`, igual que el resto de rutas de chat hoy
 * (routes/api_cliente.php no exige auth en /chats/*).
 */
class ChatMensajeEnviado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Chat $chat;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat.' . $this->chat->pedido_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.nuevo';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->chat->id,
            'pedido_id' => $this->chat->pedido_id,
            'sender_id' => $this->chat->sender_id,
            'receiver_id' => $this->chat->receiver_id,
            'message' => $this->chat->message,
            'timestamp' => $this->chat->created_at?->toIso8601String(),
        ];
    }
}
