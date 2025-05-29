<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Pedido;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index($pedidoId)
    {
        return Chat::where('pedido_id', $pedidoId)
            ->orderBy('created_at')
            ->get();
    }

    public function storeCliente(Request $request)
    {
        $idMotorizado = Pedido::where('id', $request->pedido_id)->first()->id_motorizado ?? 0;

        $chat = Chat::create([
            'pedido_id' => $request->pedido_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $idMotorizado,
            'message' => $request->message,
        ]);

        return response()->json($chat, 201);
    }

    public function storeMotorizado(Request $request)
    {
        $idCliente = Pedido::where('id', $request->pedido_id)->first()->id_cliente ?? 0;

        $chat = Chat::create([
            'pedido_id' => $request->pedido_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $idCliente,
            'message' => $request->message,
        ]);

        return response()->json($chat, 201);
    }
}
