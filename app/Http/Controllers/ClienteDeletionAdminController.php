<?php

namespace App\Http\Controllers;

use App\Models\ClienteDeletionRequest;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ClienteDeletionAdminController extends Controller
{
    /**
     * Obtener todas las solicitudes pendientes
     */
    public function getPendingRequests()
    {
        try {
            $requests = ClienteDeletionRequest::with('cliente:id,nombre,apellido,email,documento,celular')
                ->where('status', 'pending')
                ->where('code_verified_at', '!=', null) // Solo las que verificaron el código
                ->orderBy('requested_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $requests
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener solicitudes pendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de solicitudes (aprobadas y rechazadas)
     */
    public function getRequestHistory(Request $request)
    {
        try {
            $status = $request->query('status'); // 'approved', 'rejected', o null para todas

            $query = ClienteDeletionRequest::with([
                'cliente:id,nombre,apellido,email,documento',
                'processedBy:id,name,email'
            ])->whereIn('status', ['approved', 'rejected']);

            if ($status && in_array($status, ['approved', 'rejected'])) {
                $query->where('status', $status);
            }

            $requests = $query->orderBy('processed_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $requests
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener historial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar solicitud y eliminar cuenta del cliente
     */
    public function approveRequest(Request $request, $id)
    {
        try {
            $deletionRequest = ClienteDeletionRequest::with('cliente')->findOrFail($id);

            if ($deletionRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta solicitud ya fue procesada',
                ], 400);
            }

            $cliente = $deletionRequest->cliente;

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Guardar información para logs
            $clienteData = [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'email' => $cliente->email,
                'documento' => $cliente->documento,
                'motivo_eliminacion' => $deletionRequest->motivo,
                'deleted_by_admin' => Auth::id(),
                'deleted_at' => now(),
            ];

            Log::info('Cliente eliminado por admin - Solicitud aprobada', $clienteData);

            // Actualizar estado de la solicitud
            $deletionRequest->status = 'approved';
            $deletionRequest->processed_at = now();
            $deletionRequest->processed_by = Auth::id();
            $deletionRequest->admin_notes = $request->admin_notes ?? null;
            $deletionRequest->save();

            // Enviar email de confirmación al cliente
            try {
                Mail::send('emails.account-deleted', [
                    'nombre' => $cliente->nombre,
                    'request_id' => $deletionRequest->request_id,
                ], function ($message) use ($cliente) {
                    $message->to($cliente->email)
                        ->subject('Tu cuenta ha sido eliminada - TRUE LOVE');
                });
            } catch (\Exception $e) {
                Log::error('Error al enviar email de confirmación: ' . $e->getMessage());
            }

            // Eliminar direcciones del cliente
            ClienteDireccion::where('id_cliente', $cliente->id)->delete();

            // Eliminar el cliente
            $cliente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aprobada y cuenta eliminada exitosamente',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al aprobar solicitud: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar solicitud
     */
    public function rejectRequest(Request $request, $id)
    {
        try {
            $request->validate([
                'admin_notes' => 'required|string',
            ]);

            $deletionRequest = ClienteDeletionRequest::with('cliente')->findOrFail($id);

            if ($deletionRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta solicitud ya fue procesada',
                ], 400);
            }

            // Actualizar estado de la solicitud
            $deletionRequest->status = 'rejected';
            $deletionRequest->processed_at = now();
            $deletionRequest->processed_by = Auth::id();
            $deletionRequest->admin_notes = $request->admin_notes;
            $deletionRequest->save();

            // Enviar email de rechazo al cliente
            $cliente = $deletionRequest->cliente;
            if ($cliente && $cliente->email) {
                try {
                    Mail::send('emails.account-deletion-rejected', [
                        'nombre' => $cliente->nombre,
                        'request_id' => $deletionRequest->request_id,
                        'motivo_rechazo' => $request->admin_notes,
                    ], function ($message) use ($cliente) {
                        $message->to($cliente->email)
                            ->subject('Solicitud de eliminación de cuenta - TRUE LOVE');
                    });
                } catch (\Exception $e) {
                    Log::error('Error al enviar email de rechazo: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada exitosamente',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al rechazar solicitud: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de solicitudes
     */
    public function getStats()
    {
        try {
            $stats = [
                'pending' => ClienteDeletionRequest::where('status', 'pending')
                    ->where('code_verified_at', '!=', null)
                    ->count(),
                'approved' => ClienteDeletionRequest::where('status', 'approved')->count(),
                'rejected' => ClienteDeletionRequest::where('status', 'rejected')->count(),
                'total' => ClienteDeletionRequest::count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
