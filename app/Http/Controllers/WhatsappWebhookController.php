<?php

namespace App\Http\Controllers;

use App\Models\WhatsappLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la API de WhatsApp Cloud (Meta).
 *  - GET  : verificación de la URL (Meta envía hub.mode, hub.verify_token y hub.challenge).
 *  - POST : eventos (estado de los mensajes enviados y mensajes entrantes).
 *
 * Los mensajes entrantes solo se registran: NO se responde ni se marcan como leídos, para no abrir
 * conversaciones con quien escribe al número (cada conversación abierta puede generar costo).
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
                    $this->actualizarEstado($estado);
                }

                // Mensajes entrantes de clientes
                foreach ((array) ($valor['messages'] ?? []) as $mensaje) {
                    $id = $mensaje['id'] ?? null;
                    if (!$id) {
                        continue;
                    }
                    // Meta puede reenviar el mismo evento: se registra una sola vez
                    WhatsappLog::firstOrCreate(['message_id' => $id], [
                        'direccion' => WhatsappLog::ENTRANTE,
                        'telefono' => (string) ($mensaje['from'] ?? ''),
                        'estado' => 'recibido',
                        'motivo' => 'mensaje_entrante',
                        'contenido' => mb_substr((string) ($mensaje['text']['body'] ?? '[' . ($mensaje['type'] ?? 'otro') . ']'), 0, 255),
                        'cerrado_en' => now(),
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /** Actualiza el log del mensaje enviado: sent → delivered → read, o failed. Nunca retrocede. */
    private function actualizarEstado(array $estado): void
    {
        $log = WhatsappLog::where('message_id', $estado['id'] ?? '')->first();
        if (!$log || in_array($log->estado, ['fallido', 'leido'], true)) {
            return;
        }

        $nuevo = match ($estado['status'] ?? null) {
            'sent' => 'enviado',
            'delivered' => 'entregado',
            'read' => 'leido',
            'failed' => 'fallido',
            default => null,
        };
        if (!$nuevo) {
            return;
        }

        if ($nuevo === 'fallido') {
            $error = $estado['errors'][0] ?? [];
            $log->update([
                'estado' => 'fallido',
                'error_codigo' => isset($error['code']) ? (string) $error['code'] : null,
                'error_mensaje' => $error['title'] ?? ($error['message'] ?? null),
                'cerrado_en' => now(),
            ]);
            return;
        }

        $orden = WhatsappLog::ORDEN;
        if (($orden[$nuevo] ?? 0) <= ($orden[$log->estado] ?? 0)) {
            return;
        }

        $log->update(['estado' => $nuevo, 'cerrado_en' => $nuevo === 'leido' ? now() : null]);
    }
}
