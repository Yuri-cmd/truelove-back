<?php

namespace App\Http\Controllers;

use App\Models\WhatsappLog;
use App\Services\WhatsappCloudService;
use Illuminate\Support\Facades\Log;

class WhatsappStatsController extends Controller
{
    /** Costo aproximado por mensaje que consume cuota (plantilla de utilidad, Meta Perú). */
    private const COSTO_APROX_POR_MENSAJE = 0.03;

    public function index(WhatsappCloudService $whatsapp)
    {
        try {
            $inicioMes = now()->startOfMonth();

            $enviadosMes = WhatsappLog::where('direccion', WhatsappLog::SALIENTE)
                ->where('created_at', '>=', $inicioMes)
                ->count();

            $consumenCuota = WhatsappLog::consumenCuotaEsteMes()->count();

            $gratuitosMes = max(0, $enviadosMes - $consumenCuota);

            $cargosAproximados = round($consumenCuota * self::COSTO_APROX_POR_MENSAJE, 2);

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
