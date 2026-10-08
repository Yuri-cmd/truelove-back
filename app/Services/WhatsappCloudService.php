<?php

namespace App\Services;

use App\Models\WhatsappLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de mensajes por la API de WhatsApp Cloud de Meta.
 * https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 *
 * El token (META_TOKEN) nunca se registra en logs ni en las respuestas.
 *
 * Reglas de costo:
 *  - Cada envío queda en la tabla whatsapp_logs.
 *  - Solo se envían plantillas. No hay texto libre ni respuestas: así no se abren conversaciones
 *    (de pago) con quien nos escribe.
 *  - Cada número tiene un límite de envíos (uno cada N segundos, y máximo por hora y por día): si lo
 *    supera no se envía y se responde ok=false, limitado=true, reintentar_en=<segundos>.
 *  - Al llegar a la cuota mensual gratuita (WHATSAPP_CUOTA_MENSUAL menos una reserva) ya no se
 *    llama a la API: el envío se registra como omitido y se responde ok=true, omitido=true.
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

    /** Mensajes que ya gastaron cuota este mes. */
    public function usadosEsteMes(): int
    {
        return WhatsappLog::consumenCuotaEsteMes()->count();
    }

    /** Máximo de envíos del mes: la cuota gratuita menos una reserva por si hay envíos simultáneos. */
    public function limiteMensual(): int
    {
        return max(0, (int) config('services.whatsapp.cuota_mensual') - (int) config('services.whatsapp.cuota_reserva'));
    }

    public function cuotaAgotada(): bool
    {
        return $this->usadosEsteMes() >= $this->limiteMensual();
    }

    /**
     * Segundos que faltan para que este número pueda recibir otro mensaje, o 0 si ya puede.
     * Cuenta todos los envíos al número (también los fallidos u omitidos), para frenar reintentos en cadena.
     */
    public function segundosHastaPoderEnviar(string $telefono): int
    {
        $limites = [
            [(int) config('services.whatsapp.limite_intervalo_seg'), 1],
            [3600, (int) config('services.whatsapp.limite_por_hora')],
            [86400, (int) config('services.whatsapp.limite_por_dia')],
        ];

        $espera = 0;
        foreach ($limites as [$ventana, $maximo]) {
            if ($ventana <= 0 || $maximo <= 0) {
                continue;
            }
            $envios = WhatsappLog::where('direccion', WhatsappLog::SALIENTE)
                ->where('telefono', $telefono)
                ->where('created_at', '>=', now()->subSeconds($ventana))
                ->orderBy('created_at')
                ->pluck('created_at');

            if ($envios->count() >= $maximo) {
                // Puede volver a enviar cuando salga de la ventana el envío que lo deja al límite
                $mas_antiguo_que_cuenta = $envios[$envios->count() - $maximo];
                $espera = max($espera, (int) ceil($mas_antiguo_que_cuenta->copy()->addSeconds($ventana)->diffInSeconds(now(), true)));
            }
        }

        return $espera;
    }

    /**
     * Mensaje con una plantilla aprobada (la única forma de iniciar una conversación).
     *
     * Si la cuota del mes se agotó no llama a la API: devuelve ok=true y omitido=true, para que
     * el flujo que lo pidió (por ejemplo la verificación del celular) continúe sin gastar.
     *
     * @param array<int, string> $parametrosCuerpo valores de {{1}}, {{2}}… del cuerpo
     */
    public function enviarPlantilla(
        string $para,
        string $plantilla,
        string $idioma = 'es',
        array $parametrosCuerpo = [],
        ?string $motivo = null,
        ?int $idCliente = null,
        ?string $codigoBoton = null
    ): array {
        $telefono = $this->normalizarTelefono($para);

        // Límite por número: no se registra ni se envía nada (así un abuso tampoco llena la tabla)
        if ($espera = $this->segundosHastaPoderEnviar($telefono)) {
            return [
                'ok' => false, 'limitado' => true, 'reintentar_en' => $espera,
                'status' => 429, 'message_id' => null,
                'error' => ['message' => "Espera {$espera} s antes de pedir otro código"], 'datos' => [],
            ];
        }

        $registro = WhatsappLog::create([
            'direccion' => WhatsappLog::SALIENTE,
            'telefono' => $telefono,
            'motivo' => $motivo,
            'plantilla' => $plantilla,
            'id_cliente' => $idCliente,
            'estado' => 'pendiente',
        ]);

        if ($this->cuotaAgotada()) {
            $registro->update(['estado' => 'omitido_cuota', 'cerrado_en' => now()]);
            Log::warning('WhatsApp Cloud: cuota mensual agotada, no se envía', ['to' => $telefono]);
            return ['ok' => true, 'omitido' => true, 'log_id' => $registro->id, 'status' => 0, 'message_id' => null, 'error' => null, 'datos' => []];
        }

        $plantillaPayload = ['name' => $plantilla, 'language' => ['code' => $idioma]];
        $componentes = [];
        if ($parametrosCuerpo) {
            $componentes[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($parametrosCuerpo)),
            ];
        }
        // Las plantillas de Autenticación llevan el código también en el botón "Copiar código"
        if ($codigoBoton !== null) {
            $componentes[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $codigoBoton]],
            ];
        }
        if ($componentes) {
            $plantillaPayload['components'] = $componentes;
        }

        $resultado = $this->enviar([
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'template',
            'template' => $plantillaPayload,
        ]);

        $registro->update($resultado['ok']
            ? ['estado' => 'enviado', 'message_id' => $resultado['message_id'], 'http_status' => $resultado['status']]
            : [
                'estado' => 'fallido',
                'http_status' => $resultado['status'],
                'error_codigo' => isset($resultado['error']['code']) ? (string) $resultado['error']['code'] : null,
                'error_mensaje' => $resultado['error']['message'] ?? null,
                'cerrado_en' => now(),
            ]);

        $resultado['log_id'] = $registro->id;

        return $resultado;
    }

    /**
     * Envía el código de verificación con la plantilla de Autenticación configurada.
     * Mismas reglas que enviarPlantilla: límite por número, cuota mensual y registro en whatsapp_logs.
     */
    public function enviarCodigo(string $para, string $codigo, ?int $idCliente = null): array
    {
        return $this->enviarPlantilla(
            $para,
            (string) config('services.whatsapp.plantilla_codigo'),
            (string) config('services.whatsapp.plantilla_codigo_idioma'),
            [$codigo],
            'verificacion',
            $idCliente,
            $codigo
        );
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
