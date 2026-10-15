<?php

namespace App\Models;

use App\Events\ChatMensajeEnviado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Chat extends Model
{
    protected $table = 'chats';

    protected $fillable = [
        'pedido_id',
        'sender_id',
        'receiver_id',
        'message',
    ];

    /**
     * Ver el comentario en App\Models\PedidoTracking::booted(): el try/catch
     * es obligatorio para que enviar un mensaje nunca falle solo porque
     * Reverb esté caído o no desplegado — el socket es un plus, no un
     * requisito para que el chat funcione.
     */
    protected static function booted(): void
    {
        static::created(function (Chat $chat) {
            try {
                ChatMensajeEnviado::dispatch($chat);
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir ChatMensajeEnviado: ' . $e->getMessage());
            }
        });
    }

    //cliente
    public function sender()
    {
        return $this->belongsTo(Cliente::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
