<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PedidoCancelacionSolicitud;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PedidoAdminController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Listado de pedidos para el módulo de Pedidos del admin, con filtros.
     */
    public function index(Request $request)
    {
        $query = Pedido::with([
            'trackings' => function ($q) {
                $q->orderBy('id', 'desc');
            }
        ]);

        // Compatibilidad con el atajo antiguo ?fecha=hoy
        $fecha = $request->query('fecha');
        if ($fecha === 'hoy') {
            $query->whereDate('created_at', now()->toDateString());
        }

        $fechaDesde = $request->query('fecha_desde');
        if ($fechaDesde) {
            $query->whereDate('created_at', '>=', $fechaDesde);
        }

        $fechaHasta = $request->query('fecha_hasta');
        if ($fechaHasta) {
            $query->whereDate('created_at', '<=', $fechaHasta);
        }

        if ($request->query('local_id')) {
            $query->where('id_local', $request->query('local_id'));
        }

        $localBusqueda = $request->query('local');
        if ($localBusqueda) {
            $localIds = Establecimiento::where('nombre_establecimiento', 'like', '%' . $localBusqueda . '%')
                ->pluck('business_registration_id');
            $query->whereIn('id_local', $localIds);
        }

        // El estado se filtra a nivel de query (no post-paginación) porque no es
        // una columna de "pedidos": es el último estado en pedido_trackings.
        $estado = $request->query('estado');
        if ($estado !== null && $estado !== '') {
            $query->whereIn('id', function ($q) use ($estado) {
                $q->select('pedido_id')
                    ->from('pedido_trackings as pt1')
                    ->where('estado', $estado)
                    ->whereRaw('pt1.id = (SELECT MAX(id) FROM pedido_trackings as pt2 WHERE pt2.pedido_id = pt1.pedido_id)');
            });
        }

        $pedidos = $query->orderBy('created_at', 'desc')->paginate(30);

        $pedidos->getCollection()->transform(function ($pedido) {
            return $this->enrich($pedido);
        });

        return response()->json($pedidos);
    }

    public function show($id)
    {
        $pedido = Pedido::with([
            'trackings' => function ($q) {
                $q->orderBy('id', 'desc');
            },
            'detalles',
        ])->find($id);

        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        return response()->json($this->enrich($pedido));
    }

    /**
     * El admin cambia el estado del pedido manualmente, sin las restricciones
     * normales de transición (tiene autoridad total sobre el pedido).
     */
    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|integer|in:0,1,2,3,4,5,6,7,8,9',
        ]);

        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        $estado = (int) $request->estado;

        $tracking = new PedidoTracking();
        $tracking->pedido_id = $id;
        $tracking->estado = $estado;
        $tracking->setTraceability($request);
        $tracking->save();

        try {
            $cliente = Cliente::find($pedido->id_cliente);
            if ($cliente && $cliente->token_fmc) {
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    estadoPedido($estado),
                    mensajeNotificacionPedido($estado, $pedido->id, 'cliente'),
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string) $pedido->id,
                        'progress' => (string) progresoPedido($estado),
                    ],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            }
        } catch (\Exception $e) {
            Log::error('Error al notificar cambio de estado (admin): ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Estado del pedido actualizado correctamente',
        ]);
    }

    /**
     * El admin corrige la fecha de creación (created_at) de un pedido.
     * Solo permitido para pedidos ya entregados (estado 8), para corregir
     * registros históricos sin afectar el flujo operativo de pedidos activos.
     */
    public function updateFecha(Request $request, $id)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);

        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest('id')->first();
        if (!$ultimoTracking || (int) $ultimoTracking->estado !== 8) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo se puede editar la fecha de pedidos ya entregados (estado 8).',
            ], 400);
        }

        $pedido->created_at = Carbon::parse($request->fecha);
        $pedido->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Fecha del pedido actualizada correctamente',
            'created_at' => $pedido->created_at->toIso8601String(),
        ]);
    }

    private function enrich($pedido)
    {
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $cliente = Cliente::find($pedido->id_cliente);
        $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado) : null;

        $ultimoTracking = $pedido->trackings->first();
        $pedido->ultimo_estado_tracking = $ultimoTracking ? (int) $ultimoTracking->estado : null;
        $pedido->estado_label = $ultimoTracking ? estadoPedido($ultimoTracking->estado) : 'Sin seguimiento';
        $pedido->local = $local ? $local->nombre_establecimiento : null;
        $pedido->cliente = $cliente ? trim($cliente->nombre . ' ' . $cliente->apellido) : null;
        $pedido->celular_cliente = $cliente ? $cliente->celular : null;
        $pedido->motorizado_nombre = $motorizado ? trim($motorizado->nombres . ' ' . $motorizado->apellidos) : null;

        $solicitudPendiente = PedidoCancelacionSolicitud::where('pedido_id', $pedido->id)
            ->where('status', 'pending')
            ->first();
        $pedido->solicitud_cancelacion_pendiente = $solicitudPendiente;

        return $pedido;
    }
}
