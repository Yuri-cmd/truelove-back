<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\NotificationLog;
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
