<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\NotificationLog;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class NotificationTrackingController extends Controller
{
    /**
     * Listar notification logs para el admin con filtros y paginación
     */
    public function index(Request $request)
    {
        try {
            $query = NotificationLog::query()->orderBy('created_at', 'desc');

            // Filtro por app_name
            if ($request->filled('app_name')) {
                $query->where('app_name', $request->app_name);
            }

            // Filtro por user_type
            if ($request->filled('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            // Filtro por estado de entrega
            if ($request->filled('estado')) {
                switch ($request->estado) {
                    case 'enviado':
                        $query->whereNotNull('sent_at');
                        break;
                    case 'recibido':
                        $query->whereNotNull('received_at');
                        break;
                    case 'abierto':
                        $query->whereNotNull('opened_at');
                        break;
                    case 'no_recibido':
                        $query->whereNotNull('sent_at')->whereNull('received_at');
                        break;
                }
            }

            // Búsqueda por título o body
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%")
                      ->orWhere('user_id', $search);
                });
            }

            // Filtro por fecha
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->fecha_hasta);
            }

            $perPage = $request->input('per_page', 20);
            $logs = $query->paginate($perPage);

            // Estadísticas generales
            $stats = [
                'total' => NotificationLog::count(),
                'enviados' => NotificationLog::whereNotNull('sent_at')->count(),
                'recibidos' => NotificationLog::whereNotNull('received_at')->count(),
                'abiertos' => NotificationLog::whereNotNull('opened_at')->count(),
            ];

            return response()->json([
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo notification logs: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los logs de notificaciones'
            ], 500);
        }
    }

    /**
     * Listar notificaciones de un cliente para la campanita del app
     */
    public function misNotificaciones($idCliente)
    {
        try {
            $notificaciones = NotificationLog::where('user_type', 'cliente')
                ->where('user_id', $idCliente)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            $noLeidas = NotificationLog::where('user_type', 'cliente')
                ->where('user_id', $idCliente)
                ->whereNull('opened_at')
                ->count();

            return response()->json([
                'data' => $notificaciones,
                'no_leidas' => $noLeidas,
            ]);
        } catch (\Exception $e) {
            Log::error("Error obteniendo notificaciones del cliente: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las notificaciones'
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones de un cliente como leídas (botón "marcar todas")
     */
    public function marcarTodasLeidas($idCliente)
    {
        try {
            NotificationLog::where('user_type', 'cliente')
                ->where('user_id', $idCliente)
                ->whereNull('opened_at')
                ->update(['opened_at' => now()]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error("Error marcando notificaciones como leídas: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al marcar las notificaciones como leídas'
            ], 500);
        }
    }

    /**
     * Diagnóstico del repartidor (pantalla "Diagnóstico de notificaciones" de la app):
     * si el servidor tiene su token, si coincide con el del teléfono y cuántos avisos
     * de los últimos 7 días confirmó la app.
     */
    public function resumenMotorizado(Request $request, $id)
    {
        $m = RepartoRegistro::find($id);
        if (!$m) {
            return response()->json(['message' => 'Repartidor no encontrado'], 404);
        }

        $logs = NotificationLog::where('user_type', 'motorizado')->where('user_id', $id)
            ->where('created_at', '>=', now()->subDays(7));

        $tokenTelefono = (string) $request->input('token', '');

        return response()->json([
            'token_en_servidor' => !empty($m->token_fmc),
            // Solo si el teléfono manda su token: el servidor responde si es el mismo, sin exponer el suyo
            'token_coincide' => ($tokenTelefono !== '' && !empty($m->token_fmc)) ? hash_equals($m->token_fmc, $tokenTelefono) : null,
            'enviados_7d' => (clone $logs)->count(),
            'recibidos_7d' => (clone $logs)->whereNotNull('received_at')->count(),
            'ultimo_recibido' => NotificationLog::where('user_type', 'motorizado')->where('user_id', $id)
                ->whereNotNull('received_at')->max('received_at'),
        ]);
    }

    /** Manda un aviso de prueba al repartidor y dice qué respondió Google. */
    public function probarMotorizado($id, FirebaseService $firebase)
    {
        $m = RepartoRegistro::find($id);
        if (!$m) {
            return response()->json(['message' => 'Repartidor no encontrado'], 404);
        }
        if (empty($m->token_fmc)) {
            return response()->json(['resultado' => 'sin_token']);
        }

        $respuesta = $firebase->sendNotification(
            $m->token_fmc,
            '✅ Prueba de notificaciones',
            'Si ves este aviso, tu teléfono recibe las notificaciones de TrueLove.',
            ['type' => 'diagnostico'],
            'motorizado',
            $m->id,
            'motorizado'
        );

        if (isset($respuesta['name'])) {
            return response()->json(['resultado' => 'enviado']);
        }

        $estado = $respuesta['error']['status'] ?? null;
        return response()->json([
            // UNREGISTERED: Google ya no reconoce ese token (app reinstalada o datos borrados)
            'resultado' => $estado === 'NOT_FOUND' || $estado === 'UNREGISTERED' ? 'token_invalido' : 'error',
            'detalle' => $respuesta['error']['message'] ?? null,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notification_logs,id',
            'status' => 'required|in:received,opened',
        ]);

        try {
            $log = NotificationLog::findOrFail($request->notification_id);
            
            if ($request->status === 'received') {
                $log->update(['received_at' => now()]);
            } elseif ($request->status === 'opened') {
                $log->update(['opened_at' => now()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notification status updated'
            ]);
        } catch (\Exception $e) {
            Log::error("Error actualizando trazabilidad de notificación: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update notification status'
            ], 500);
        }
    }
}
