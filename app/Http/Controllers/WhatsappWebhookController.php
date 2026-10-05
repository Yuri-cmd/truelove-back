<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la API de WhatsApp Cloud (Meta).
 *  - GET  : verificación de la URL (Meta envía hub.mode, hub.verify_token y hub.challenge).
 *  - POST : eventos (estado de los mensajes enviados y mensajes entrantes).
 */
class WhatsappWebhookController extends Controller
{
    /** Verificación: Meta espera recibir el hub.challenge en texto plano. */
    public function verificar(Request $request)
    {
        $esperado = (string) config('services.whatsapp.webhook_verify_token');

        if ($esperado !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($esperado, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook: verificación rechazada');
        return response('Forbidden', 403);
    }

    /** Recibe los eventos. Siempre responde 200 rápido para que Meta no reintente. */
    public function recibir(Request $request)
    {
        // Si hay secreto de la app configurado, solo se aceptan eventos firmados por Meta.
        $secreto = (string) config('services.whatsapp.app_secret');
        if ($secreto !== '') {
            $firma = (string) $request->header('X-Hub-Signature-256');
            $calculada = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secreto);
            if (!hash_equals($calculada, $firma)) {
                Log::warning('WhatsApp webhook: firma inválida');
                return response('Forbidden', 403);
            }
        }

        foreach ((array) $request->input('entry', []) as $entrada) {
            foreach ((array) ($entrada['changes'] ?? []) as $cambio) {
                $valor = $cambio['value'] ?? [];

                // Estado de mensajes enviados: sent, delivered, read, failed
                foreach ((array) ($valor['statuses'] ?? []) as $estado) {
                    Log::info('WhatsApp estado', [
                        'message_id' => $estado['id'] ?? null,
                        'estado' => $estado['status'] ?? null,
                        'para' => $estado['recipient_id'] ?? null,
                        'errores' => $estado['errors'] ?? null,
                    ]);
                }

                // Mensajes entrantes de clientes
                foreach ((array) ($valor['messages'] ?? []) as $mensaje) {
                    Log::info('WhatsApp mensaje entrante', [
                        'message_id' => $mensaje['id'] ?? null,
                        'de' => $mensaje['from'] ?? null,
                        'tipo' => $mensaje['type'] ?? null,
                        'texto' => $mensaje['text']['body'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
