<?php

namespace App\Http\Controllers;

use App\Models\WhatsappLog;
use App\Services\WhatsappCloudService;
use Illuminate\Support\Facades\Log;

class WhatsappStatsController extends Controller
{
    /**
     * Costo aproximado por mensaje que consume cuota (plantilla, Meta Perú).
     * Calculado a partir del cargo real reportado por Meta Business Suite: S/ 6.49 / 65 mensajes.
     * Configurable vía META_COSTO_POR_MENSAJE si Meta ajusta sus tarifas.
     */
    private const COSTO_APROX_POR_MENSAJE = 0.0998;

    public function index(WhatsappCloudService $whatsapp)
    {
        try {
            // Solo cuentan los mensajes que realmente salieron (tienen message_id); los
            // fallidos u omitidos por cuota no los factura ni los cuenta Meta.
            $enviadosMes = WhatsappLog::consumenCuotaEsteMes()->count();

            // No se usan mensajes de ventana de servicio al cliente (solo plantillas de pago),
            // así que no hay mensajes gratuitos en este flujo.
            $gratuitosMes = 0;

            $costoPorMensaje = (float) config('services.whatsapp.costo_por_mensaje', self::COSTO_APROX_POR_MENSAJE);
            $cargosAproximados = round($enviadosMes * $costoPorMensaje, 2);

            $cuenta = [
                'nombre' => null,
                'telefono' => null,
                'calidad' => null,
            ];

            if (!$whatsapp->configuracionFaltante()) {
                $verificacion = $whatsapp->verificar();
                if ($verificacion['ok']) {
                    $cuenta['nombre'] = $verificacion['datos']['verified_name'] ?? null;
                    $cuenta['telefono'] = $verificacion['datos']['display_phone_number'] ?? null;
                    $cuenta['calidad'] = $verificacion['datos']['quality_rating'] ?? null;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'mensajes_enviados_mes' => $enviadosMes,
                    'mensajes_gratuitos_mes' => $gratuitosMes,
                    'cargos_aproximados_mensajes' => $cargosAproximados,
                    'llamadas_mes' => 0,
                    'duracion_promedio_llamadas_seg' => 0,
                    'cargos_aproximados_llamadas' => 0,
                    'cuenta' => $cuenta,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de WhatsApp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
