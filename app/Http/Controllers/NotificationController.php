<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationController extends Controller
{
    /**
     * Stream de notificaciones en tiempo real usando Server-Sent Events
     */
    public function streamNotifications(Request $request)
    {
        // Verificar autenticación mediante token en query string
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        // Validar el token y obtener el usuario
        $user = Auth::guard('sanctum')->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $response = new StreamedResponse(function () use ($user) {
            // Configurar headers para SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Para nginx
            
            // Enviar las solicitudes iniciales
            $this->sendInitialData();
            
            // Mantener la conexión viva y enviar heartbeat cada 30 segundos
            $lastCheck = time();
            $heartbeatInterval = 30; // segundos
            
            while (true) {
                $currentTime = time();
                
                // Enviar heartbeat
                if ($currentTime - $lastCheck >= $heartbeatInterval) {
                    $this->sendHeartbeat();
                    $lastCheck = $currentTime;
                }
                
                // Verificar si hay nuevas solicitudes
                $this->checkForNewRequests();
                
                // Dormir por 2 segundos antes de la siguiente verificación
                sleep(2);
                
                // Verificar si la conexión sigue activa
                if (connection_aborted()) {
                    break;
                }
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        
        return $response;
    }

    /**
     * Enviar datos iniciales al conectarse
     */
    private function sendInitialData()
    {
        try {
            $requests = AccountDeletionRequest::with(['user' => function($query) {
                $query->select('id', 'name', 'email', 'usuario');
            }])
            ->where('status', 'pending')
            ->orderBy('requested_at', 'desc')
            ->get();

            $data = [
                'type' => 'deletion_requests',
                'requests' => $requests,
                'count' => $requests->count()
            ];

            echo "data: " . json_encode($data) . "\n\n";
            flush();

        } catch (\Exception $e) {
            Log::error('Error sending initial SSE data: ' . $e->getMessage());
        }
    }

    /**
     * Verificar nuevas solicitudes (esto debería ser optimizado con eventos en un caso real)
     */
    private function checkForNewRequests()
    {
        static $lastRequestId = null;
        
        try {
            // Obtener la solicitud más reciente
            $latestRequest = AccountDeletionRequest::with(['user' => function($query) {
                $query->select('id', 'name', 'email', 'usuario');
            }])
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->first();

            // Si hay una nueva solicitud, enviarla
            if ($latestRequest && $latestRequest->id !== $lastRequestId) {
                $data = [
                    'type' => 'new_deletion_request',
                    'request' => $latestRequest
                ];

                echo "data: " . json_encode($data) . "\n\n";
                flush();

                $lastRequestId = $latestRequest->id;
            }

        } catch (\Exception $e) {
            Log::error('Error checking for new requests: ' . $e->getMessage());
        }
    }

    /**
     * Enviar heartbeat para mantener la conexión viva
     */
    private function sendHeartbeat()
    {
        $data = [
            'type' => 'heartbeat',
            'timestamp' => time()
        ];

        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }

    /**
     * Notificar cuando una solicitud ha sido procesada
     */
    public static function notifyRequestProcessed($requestId)
    {
        // En un caso real, esto se haría con un sistema de colas o eventos
        // Por ahora, solo log para debugging
        Log::info("Request {$requestId} has been processed");
    }
}