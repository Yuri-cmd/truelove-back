<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AccountDeletionController extends Controller
{
    /**
     * Solicitar la eliminación de la cuenta del usuario autenticado
     */
    public function requestDeletion(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Verificar si ya existe una solicitud pendiente
            $existingRequest = AccountDeletionRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();
                
            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una solicitud de eliminación pendiente'
                ], 400);
            }
            
            // Crear la solicitud
            $deletionRequest = new AccountDeletionRequest();
            $deletionRequest->user_id = $user->id;
            $deletionRequest->reason = $request->input('reason');
            $deletionRequest->requested_at = now();
            $deletionRequest->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de eliminación enviada correctamente'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error al solicitar eliminación de cuenta: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener todas las solicitudes de eliminación pendientes (solo admin)
     */
    public function getPendingRequests()
    {
        try {
            $requests = AccountDeletionRequest::with(['user' => function($query) {
                $query->select('id', 'name', 'email', 'usuario');
            }])
            ->where('status', 'pending')
            ->orderBy('requested_at', 'desc')
            ->get();
            
            return response()->json($requests);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener solicitudes de eliminación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las solicitudes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Aprobar una solicitud de eliminación y eliminar el usuario
     */
    public function approveRequest($id)
    {
        try {
            $deletionRequest = AccountDeletionRequest::findOrFail($id);
            $user = User::findOrFail($deletionRequest->user_id);
            
            // Actualizar el estado de la solicitud
            $deletionRequest->status = 'approved';
            $deletionRequest->processed_at = now();
            $deletionRequest->processed_by = Auth::id();
            $deletionRequest->save();
            
            // Guardar información del usuario para el registro
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'deleted_by' => Auth::id(),
                'deleted_at' => now()
            ];
            
            // Opcional: Guardar registro de eliminación en otra tabla o log
            Log::info('Usuario eliminado por solicitud', $userData);
            
            // Eliminar el usuario
            $user->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al aprobar solicitud de eliminación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Rechazar una solicitud de eliminación
     */
    public function rejectRequest($id)
    {
        try {
            $deletionRequest = AccountDeletionRequest::findOrFail($id);
            
            // Actualizar el estado de la solicitud
            $deletionRequest->status = 'rejected';
            $deletionRequest->processed_at = now();
            $deletionRequest->processed_by = Auth::id();
            $deletionRequest->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al rechazar solicitud de eliminación: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener el historial de solicitudes de eliminación (opcional)
     */
    public function getRequestHistory()
    {
        try {
            $requests = AccountDeletionRequest::with(['user:id,name,email,usuario', 'processor:id,name'])
                ->orderBy('requested_at', 'desc')
                ->paginate(15);
                
            return response()->json($requests);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener historial de solicitudes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}