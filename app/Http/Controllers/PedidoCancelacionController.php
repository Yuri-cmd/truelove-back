<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PedidoCancelacionSolicitud;
use App\Models\PedidoTracking;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PedidoCancelacionController extends Controller
{
    // Estados de pedido a partir de los cuales se considera "ya recogido" por el motorizado
    private const ESTADOS_RECOGIDO = [5, 6, 7];

    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * El socio solicita la cancelación de un pedido ya recogido por el motorizado.
     * Requiere aprobación del admin para hacerse efectiva.
     */
    public function requestCancellation(Request $request, $pedidoId)
    {
        $request->validate([
            'motivo' => 'required|string|min:5|max:1000',
        ]);

        $pedido = Pedido::find($pedidoId);
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        $ultimoTracking = PedidoTracking::where('pedido_id', $pedidoId)->latest('id')->first();
        $estadoActual = $ultimoTracking ? (int) $ultimoTracking->estado : null;

        if ($estadoActual === null || !in_array($estadoActual, self::ESTADOS_RECOGIDO, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este pedido solo puede solicitarse en cancelación cuando ya fue recogido por el motorizado. Para otros estados, cancele el pedido directamente.',
            ], 400);
        }

        $solicitudExistente = PedidoCancelacionSolicitud::where('pedido_id', $pedidoId)
            ->where('status', 'pending')
            ->first();

        if ($solicitudExistente) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ya existe una solicitud de cancelación pendiente de aprobación para este pedido.',
            ], 400);
        }

        $solicitud = PedidoCancelacionSolicitud::create([
            'pedido_id' => $pedidoId,
            'estado_pedido_al_solicitar' => $estadoActual,
            'motivo' => $request->motivo,
            'status' => 'pending',
            'solicitado_por_socio_id' => $pedido->id_local,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Solicitud de cancelación enviada. Un administrador debe aprobarla para que el pedido se cancele.',
            'solicitud' => $solicitud,
        ], 201);
    }

    /**
     * Listado de solicitudes pendientes (para el módulo de Pedidos del admin).
     */
    public function getPendingRequests()
    {
        $solicitudes = PedidoCancelacionSolicitud::with(['pedido'])
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        $solicitudes->transform(function ($solicitud) {
            return $this->enrich($solicitud);
        });

        return response()->json($solicitudes);
    }

    /**
     * Historial de solicitudes ya procesadas (aprobadas/rechazadas).
     */
    public function getRequestHistory(Request $request)
    {
        $query = PedidoCancelacionSolicitud::with(['pedido', 'revisor:id,name'])
            ->where('status', '!=', 'pending')
            ->orderBy('revisado_at', 'desc');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $solicitudes = $query->paginate(15);
        $solicitudes->getCollection()->transform(function ($solicitud) {
            return $this->enrich($solicitud);
        });

        return response()->json($solicitudes);
    }

    /**
     * Aprobar la solicitud: cancela el pedido de forma efectiva.
     */
    public function approveRequest(Request $request, $id)
    {
        $solicitud = PedidoCancelacionSolicitud::find($id);
        if (!$solicitud) {
            return response()->json(['status' => 'error', 'message' => 'Solicitud no encontrada'], 404);
        }

        if ($solicitud->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Esta solicitud ya fue procesada'], 400);
        }

        $pedido = Pedido::find($solicitud->pedido_id);
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        $ultimoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest('id')->first();
        if (!$ultimoTracking || in_array((int) $ultimoTracking->estado, [0, 8], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'El pedido ya cambió a un estado final (entregado/cancelado); no se puede aprobar esta solicitud. Puede declinarla.',
            ], 400);
        }

        $tracking = new PedidoTracking();
        $tracking->pedido_id = $pedido->id;
        $tracking->estado = 0;
        $tracking->setTraceability($request);
        $tracking->save();

        $solicitud->status = 'approved';
        $solicitud->revisado_por_admin_id = Auth::id();
        $solicitud->revisado_at = now();
        $solicitud->save();

        $this->notificarCliente($pedido, 'Tu pedido #' . $pedido->id . ' ha sido cancelado.');

        return response()->json([
            'status' => 'success',
            'message' => 'Solicitud aprobada. El pedido fue cancelado.',
        ]);
    }

    /**
     * Declinar la solicitud: el pedido continúa su curso normal.
     */
    public function rejectRequest(Request $request, $id)
    {
        $solicitud = PedidoCancelacionSolicitud::find($id);
        if (!$solicitud) {
            return response()->json(['status' => 'error', 'message' => 'Solicitud no encontrada'], 404);
        }

        if ($solicitud->status !== 'pending') {
            return response()->json(['status' => 'error', 'message' => 'Esta solicitud ya fue procesada'], 400);
        }

        $solicitud->status = 'rejected';
        $solicitud->revisado_por_admin_id = Auth::id();
        $solicitud->revisado_at = now();
        $solicitud->save();

        $pedido = Pedido::find($solicitud->pedido_id);
        if ($pedido) {
            $this->notificarSocio($pedido, 'Tu solicitud de cancelación para el pedido #' . $pedido->id . ' fue declinada. El pedido continúa su curso normal.');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Solicitud declinada. El pedido continúa su curso normal.',
        ]);
    }

    private function enrich($solicitud)
    {
        $pedido = $solicitud->pedido;
        if ($pedido) {
            $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
            $cliente = Cliente::find($pedido->id_cliente);
            $solicitud->pedido->local = $local ? $local->nombre_establecimiento : null;
            $solicitud->pedido->cliente = $cliente ? trim($cliente->nombre . ' ' . $cliente->apellido) : null;
        }
        return $solicitud;
    }

    private function notificarCliente(Pedido $pedido, string $mensaje)
    {
        $cliente = Cliente::find($pedido->id_cliente);
        if ($cliente && $cliente->token_fmc) {
            try {
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    estadoPedido(0),
                    $mensaje,
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string) $pedido->id,
                        'progress' => (string) progresoPedido(0),
                    ],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            } catch (\Exception $e) {
                Log::error('Error al notificar cliente sobre cancelación de pedido: ' . $e->getMessage());
            }
        }
    }

    private function notificarSocio(Pedido $pedido, string $mensaje)
    {
        $local = BusinessRegistration::find($pedido->id_local);
        if ($local && $local->token_fmc) {
            try {
                $this->firebaseService->sendNotification(
                    $local->token_fmc,
                    'Solicitud de cancelación declinada',
                    $mensaje,
                    [],
                    'socio',
                    $pedido->id_local,
                    'socio'
                );
            } catch (\Exception $e) {
                Log::error('Error al notificar socio sobre solicitud de cancelación declinada: ' . $e->getMessage());
            }
        }
    }
}
