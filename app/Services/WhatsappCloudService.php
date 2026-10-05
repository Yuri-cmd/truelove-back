<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de mensajes por la API de WhatsApp Cloud de Meta.
 * https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 *
 * El token (META_TOKEN) nunca se registra en logs ni en las respuestas.
 */
class WhatsappCloudService
{
    private const PAIS_POR_DEFECTO = '51'; // Perú

    private function config(string $clave): ?string
    {
        return config("services.whatsapp.$clave");
    }

    private function url(string $ruta): string
    {
        return 'https://graph.facebook.com/' . $this->config('graph_version') . '/' . ltrim($ruta, '/');
    }

    private function http()
    {
        return Http::withToken((string) $this->config('token'))
            ->acceptJson()
            ->timeout(20);
    }

    /** Falta configuración? Devuelve el nombre de la variable que falta o null. */
    public function configuracionFaltante(): ?string
    {
        foreach (['token' => 'META_TOKEN', 'phone_number_id' => 'META_PHONE_NUMBER_ID'] as $clave => $env) {
            if (!$this->config($clave)) {
                return $env;
            }
        }
        return null;
    }

    /**
     * Número en el formato que pide Meta: solo dígitos con código de país, sin "+".
     * Un celular peruano de 9 dígitos recibe el prefijo 51.
     */
    public function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono);
        if (strlen($digitos) === 9) {
            $digitos = self::PAIS_POR_DEFECTO . $digitos;
        }
        return $digitos;
    }

    /**
     * Comprueba que el token y el Phone Number ID funcionan, sin enviar nada.
     *
     * @return array{ok: bool, status: int, datos: array}
     */
    public function verificar(): array
    {
        $respuesta = $this->http()->get(
            $this->url($this->config('phone_number_id')),
            ['fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status']
        );

        return ['ok' => $respuesta->successful(), 'status' => $respuesta->status(), 'datos' => $respuesta->json() ?? []];
    }

    /**
     * Mensaje con una plantilla aprobada (la única forma de iniciar una conversación).
     *
     * @param array<int, string> $parametrosCuerpo valores de {{1}}, {{2}}… del cuerpo
     */
    public function enviarPlantilla(string $para, string $plantilla, string $idioma = 'es', array $parametrosCuerpo = []): array
    {
        $plantillaPayload = ['name' => $plantilla, 'language' => ['code' => $idioma]];
        if ($parametrosCuerpo) {
            $plantillaPayload['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($parametrosCuerpo)),
            ]];
        }

        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizarTelefono($para),
            'type' => 'template',
            'template' => $plantillaPayload,
        ]);
    }

    /** Texto libre: solo llega si el cliente escribió en las últimas 24 horas. */
    public function enviarTexto(string $para, string $texto): array
    {
        return $this->enviar([
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizarTelefono($para),
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $texto],
        ]);
    }

    /** Cuerpo exacto que se enviaría (para la opción --dry-run). */
    public function payloadPlantilla(string $para, string $plantilla, string $idioma, array $parametros): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizarTelefono($para),
            'type' => 'template',
            'template' => ['name' => $plantilla, 'language' => ['code' => $idioma]],
        ];
        if ($parametros) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($parametros)),
            ]];
        }
        return $payload;
    }

    /**
     * @return array{ok: bool, status: int, message_id: ?string, error: ?array, datos: array}
     */
    private function enviar(array $payload): array
    {
        try {
            $respuesta = $this->http()->post($this->url($this->config('phone_number_id') . '/messages'), $payload);
        } catch (\Throwable $e) {
            Log::error('WhatsApp Cloud: fallo de red: ' . $e->getMessage());
            return ['ok' => false, 'status' => 0, 'message_id' => null, 'error' => ['message' => $e->getMessage()], 'datos' => []];
        }

        $datos = $respuesta->json() ?? [];
        $resultado = [
            'ok' => $respuesta->successful(),
            'status' => $respuesta->status(),
            'message_id' => $datos['messages'][0]['id'] ?? null,
            'error' => $datos['error'] ?? null,
            'datos' => $datos,
        ];

        if (!$resultado['ok']) {
            Log::warning('WhatsApp Cloud: envío rechazado', [
                'status' => $resultado['status'],
                'to' => $payload['to'] ?? null,
                'error' => $resultado['error'],
            ]);
        }

        return $resultado;
    }
}
