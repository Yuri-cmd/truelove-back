<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(private FirebaseService $firebaseService)
    {
    }

    public function index($pedidoId)
    {
        return Chat::where('pedido_id', $pedidoId)
            ->orderBy('created_at')
            ->get();
    }

    public function storeCliente(Request $request)
    {
        $pedido = Pedido::where('id', $request->pedido_id)->first();
        $idMotorizado = $pedido->id_motorizado ?? 0;

        $chat = Chat::create([
            'pedido_id' => $request->pedido_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $idMotorizado,
            'message' => $request->message,
        ]);

        // Aviso al repartidor (después de responder, para no demorar el envío)
        if ($pedido && $idMotorizado) {
            $this->avisarMensaje(function () use ($pedido, $idMotorizado, $request) {
                $motorizado = RepartoRegistro::find($idMotorizado);
                if (!$motorizado || !$motorizado->token_fmc) {
                    return;
                }
                $cliente = Cliente::find($pedido->id_cliente);
                $this->firebaseService->sendNotification(
                    $motorizado->token_fmc,
                    '💬 ' . ($cliente->nombre ?? 'Tu cliente'),
                    $this->resumen($request->message),
                    ['type' => 'chat_message', 'pedido_id' => (string) $pedido->id],
                    'motorizado',
                    $motorizado->id,
                    'motorizado'
                );
            });
        }

        return response()->json($chat, 201);
    }

    public function storeMotorizado(Request $request)
    {
        $pedido = Pedido::where('id', $request->pedido_id)->first();
        $idCliente = $pedido->id_cliente ?? 0;

        $chat = Chat::create([
            'pedido_id' => $request->pedido_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $idCliente,
            'message' => $request->message,
        ]);

        // Aviso al cliente (después de responder, para no demorar el envío)
        if ($pedido && $idCliente) {
            $this->avisarMensaje(function () use ($pedido, $idCliente, $request) {
                $cliente = Cliente::find($idCliente);
                if (!$cliente || !$cliente->token_fmc) {
                    return;
                }
                $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado) : null;
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    '💬 ' . ($motorizado->nombres ?? 'Tu repartidor'),
                    $this->resumen($request->message),
                    ['type' => 'chat_message', 'pedido_id' => (string) $pedido->id],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            });
        }

        return response()->json($chat, 201);
    }

    /** Ejecuta el envío tras responder y sin que un fallo de Firebase afecte al mensaje. */
    private function avisarMensaje(callable $envio): void
    {
        app()->terminating(function () use ($envio) {
            try {
                $envio();
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el aviso de mensaje de chat: ' . $e->getMessage());
            }
        });
    }

    private function resumen($mensaje): string
    {
        $texto = trim((string) $mensaje);
        return mb_strlen($texto) > 120 ? mb_substr($texto, 0, 117) . '...' : $texto;
    }
}
