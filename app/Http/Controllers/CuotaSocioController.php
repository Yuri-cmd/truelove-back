<?php

namespace App\Http\Controllers;

use App\Models\CuotaSocio;
use App\Models\PagoCuotaSocio;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PeriodoCuotaSocio;
use App\Models\BusinessRegistration;
use App\Services\PeriodoCuotaService;
use App\Services\CalculoCuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CuotaSocioController extends Controller
{
    protected $periodoCuotaService;

    public function __construct(PeriodoCuotaService $periodoCuotaService)
    {
        $this->periodoCuotaService = $periodoCuotaService;
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * GET /admin/cuotas-socios
     * Listar todas las cuotas
     */
    public function index()
    {
        $cuotas = CuotaSocio::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $cuotas
        ]);
    }

    /**
     * POST /admin/cuotas-socios
     * Crear nueva cuota
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'periodicidad' => 'required|in:diario,semanal,quincenal,mensual',
            'tipo_cuota' => 'required|in:monto_fijo,porcentaje',
            'monto_cuota' => 'required_if:tipo_cuota,monto_fijo|nullable|numeric|min:0',
            'porcentaje_comision' => 'required_if:tipo_cuota,porcentaje|nullable|numeric|min:0|max:100',
            'minimo_pedidos' => 'nullable|integer|min:0',
            'exonerar_si_menos_pedidos' => 'nullable|boolean',
            'monto_minimo' => 'nullable|numeric|min:0',
            'monto_uso_app' => 'nullable|numeric|min:0',
            'monto_maximo' => 'nullable|numeric|min:0',
            'numero_cuenta' => 'required|string|max:50',
            'tipo_cuenta' => 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'metodos_pago_disponibles' => 'nullable|array',
            'numero_yape' => 'nullable|string|max:20',
            'titular_yape' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string'
        ]);

        // Si el tipo es porcentaje y no se proporciona monto_cuota, establecer en 0
        if ($validated['tipo_cuota'] === 'porcentaje' && !isset($validated['monto_cuota'])) {
            $validated['monto_cuota'] = 0;
        }

        // Si se marca como activa, desactivar otras cuotas activas
        // if ($request->estado === 'activo') {
        //     CuotaSocio::where('estado', 'activo')->update(['estado' => 'inactivo']);
        // }

        $cuota = CuotaSocio::create(array_merge($validated, [
            'estado' => $request->estado ?? 'activo'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Cuota creada exitosamente',
            'data' => $cuota
        ], 201);
    }

    /**
     * PUT /admin/cuotas-socios/{id}
     * Actualizar cuota
     */
    public function update(Request $request, $id)
    {
        $cuota = CuotaSocio::findOrFail($id);

        $validated = $request->validate([
            'periodicidad' => 'sometimes|in:diario,semanal,quincenal,mensual',
            'tipo_cuota' => 'sometimes|in:monto_fijo,porcentaje',
            'monto_cuota' => 'sometimes|nullable|numeric|min:0',
            'porcentaje_comision' => 'sometimes|nullable|numeric|min:0|max:100',
            'minimo_pedidos' => 'nullable|integer|min:0',
            'exonerar_si_menos_pedidos' => 'nullable|boolean',
            'monto_minimo' => 'nullable|numeric|min:0',
            'monto_uso_app' => 'nullable|numeric|min:0',
            'monto_maximo' => 'nullable|numeric|min:0',
            'numero_cuenta' => 'sometimes|string|max:50',
            'tipo_cuenta' => 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'metodos_pago_disponibles' => 'nullable|array',
            'numero_yape' => 'nullable|string|max:20',
            'titular_yape' => 'nullable|string|max:100',
            'estado' => 'sometimes|in:activo,inactivo',
            'fecha_inicio' => 'sometimes|nullable|date',
            'fecha_fin' => 'sometimes|nullable|date',
            'descripcion' => 'nullable|string'
        ]);

        // Si se activa esta cuota, desactivar las demás
        // if (isset($validated['estado']) && $validated['estado'] === 'activo') {
        //     CuotaSocio::where('id', '!=', $id)->update(['estado' => 'inactivo']);
        // }

        $cuota->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuota actualizada exitosamente',
            'data' => $cuota
        ]);
    }

    /**
     * DELETE /admin/cuotas-socios/{id}
     * Eliminar cuota
     */
    public function destroy($id)
    {
        $cuota = CuotaSocio::findOrFail($id);
        $cuota->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuota eliminada exitosamente'
        ]);
    }

    /**
     * GET /admin/cuotas-socios/activa
     * Obtener cuota activa
     */
    public function getActiva()
    {
       $cuotas = CuotaSocio::activa()->get();

        if ($cuotas->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay cuotas activas'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $cuotas // Ahora devuelve un array de cuotas
        ]);
    }

    /**
     * GET /admin/pagos-cuotas-socios
     * Ver todos los pagos/comprobantes
     */
    public function getPagos(Request $request)
    {
        $query = PagoCuotaSocio::with(['socio', 'cuota']);

        // Filtrar por estado si se proporciona
        if ($request->has('estado')) {
            $query->where('estado_pago', $request->estado);
        }

        $pagos = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $pagos
        ]);
    }

    /**
     * PUT /admin/pagos-cuotas-socios/{id}/aprobar
     * Aprobar pago
     */
    public function aprobarPago(Request $request, $id)
    {
        $pago = PagoCuotaSocio::findOrFail($id);

        $pago->update([
            'estado_pago' => 'aprobado',
            'fecha_aprobacion' => Carbon::now(),
            'aprobado_por' => $request->user()->id,
            'observaciones' => $request->observaciones
        ]);

        // Buscar el período asociado y marcarlo como pagado
        $periodo = PeriodoCuotaSocio::where('pago_id', $pago->id)->first();
        if ($periodo) {
            $this->periodoCuotaService->marcarComoPagado($periodo->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago aprobado exitosamente',
            'data' => $pago->load(['socio', 'cuota'])
        ]);
    }

    /**
     * PUT /admin/pagos-cuotas-socios/{id}/rechazar
     * Rechazar pago
     */
    public function rechazarPago(Request $request, $id)
    {
        $validated = $request->validate([
            'motivo_rechazo' => 'required|string'
        ]);

        $pago = PagoCuotaSocio::findOrFail($id);

        $pago->update([
            'estado_pago' => 'rechazado',
            'motivo_rechazo' => $validated['motivo_rechazo'],
            'aprobado_por' => $request->user()->id
        ]);

        // Buscar el período asociado y marcarlo como pendiente nuevamente
        $periodo = PeriodoCuotaSocio::where('pago_id', $pago->id)->first();
        if ($periodo) {
            $this->periodoCuotaService->marcarComoPendiente($periodo->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago rechazado',
            'data' => $pago->load(['socio', 'cuota'])
        ]);
    }

    /**
     * GET /admin/cuotas-socios/estadisticas
     * Estadísticas de pagos
     */
    public function estadisticas()
    {
        $stats = [
            'total_pagos' => PagoCuotaSocio::count(),
            'pagos_pendientes' => PagoCuotaSocio::pendientes()->count(),
            'pagos_aprobados' => PagoCuotaSocio::aprobados()->count(),
            'pagos_rechazados' => PagoCuotaSocio::rechazados()->count(),
            'monto_total_aprobado' => PagoCuotaSocio::aprobados()->sum('monto_pagado'),
            'monto_pendiente' => PagoCuotaSocio::pendientes()->sum('monto_pagado')
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // ==================== SOCIO ENDPOINTS ====================

    /**
     * GET /socio/cuota-activa
     * Ver cuota que tiene asignada el socio (autenticado)
     */
      public function getCuotaActiva(Request $request)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        // Obtener la cuota asignada al socio
        if (!$socio->cuota_socio_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes una cuota asignada en este momento'
            ], 404);
        }

        $cuota = CuotaSocio::find($socio->cuota_socio_id);

        if (!$cuota) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la cuota asignada'
            ], 404);
        }

        // Para porcentaje, dia_pago no aplica (el vencimiento es periodo_fin)
        if ($cuota->tipo_cuota === 'porcentaje') {
            $cuota->dia_pago = null;
            $cuota->dia_pago_nota = null;
        }

        return response()->json([
            'success' => true,
            'data' => $cuota // Devuelve UN objeto, no un array
        ]);
    }

    /**
     * POST /socio/subir-comprobante
     * Subir comprobante de pago (autenticado)
     */
    public function subirComprobante(Request $request)
    {
        $validated = $request->validate([
            'cuota_socio_id' => 'required|exists:cuotas_socios,id',
            'comprobante_pago' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
            'fecha_pago' => 'required|date',
            'monto_pagado' => 'required|numeric|min:0',
            'metodo_pago' => 'required|string|in:yape,transferencia,deposito',
            'numero_operacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string'
        ]);

        // Obtener ID del socio autenticado
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        // Subir imagen del comprobante
        $path = null;
        if ($request->hasFile('comprobante_pago')) {
            $path = $request->file('comprobante_pago')->store('comprobantes-cuotas', 'custom_public');
        }

        $pago = PagoCuotaSocio::create([
            'cuota_socio_id' => $validated['cuota_socio_id'],
            'socio_id' => $socio->id,
            'comprobante_pago' => $path,
            'estado_pago' => 'pendiente',
            'fecha_pago' => $validated['fecha_pago'],
            'monto_pagado' => $validated['monto_pagado'],
            'metodo_pago' => $validated['metodo_pago'],
            'numero_operacion' => $validated['numero_operacion'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comprobante subido exitosamente. En espera de aprobación.',
            'data' => $pago->load('cuota')
        ], 201);
    }

    /**
     * GET /socio/mis-pagos
     * Historial de pagos del socio (autenticado)
     */
    public function misPagos(Request $request)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        $pagos = PagoCuotaSocio::with('cuota')
                    ->where('socio_id', $socio->id)
                    ->orderBy('fecha_pago', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'data' => $pagos
        ]);
    }

    // ==================== ENDPOINTS DE PERÍODOS ====================

    /**
     * POST /admin/cuotas-socios/asignar-cuota
     * Asignar cuota a un socio y generar períodos automáticos
     */
    public function asignarCuotaASocio(Request $request)
    {
        $validated = $request->validate([
            'socio_id' => 'required|exists:business_registrations,id',
            'cuota_socio_id' => 'required|exists:cuotas_socios,id',
            'cantidad_periodos' => 'nullable|integer|min:1|max:24', // Máx 24 períodos (2 años)
            'fecha_inicio' => 'nullable|date', // Fecha de inicio personalizada
            'dia_pago' => 'nullable|integer|min:1|max:31' // Día del mes para realizar el pago
        ]);

        $socio = BusinessRegistration::findOrFail($validated['socio_id']);
        $cuota = CuotaSocio::findOrFail($validated['cuota_socio_id']);

        // Para porcentaje: el vencimiento es automáticamente el fin del período,
        // no se necesita dia_pago a menos que el admin lo especifique explícitamente
        if ($cuota->tipo_cuota === 'porcentaje') {
            if (isset($validated['dia_pago']) && $validated['dia_pago'] > 0) {
                $cuota->update([
                    'dia_pago' => $validated['dia_pago'],
                    'dia_pago_nota' => 'Día de vencimiento personalizado'
                ]);
            }
            // Si no se especifica, no forzar dia_pago (fecha_vencimiento = periodo_fin)
        } else {
            // Para monto fijo: configurar dia_pago normalmente
            if (isset($validated['dia_pago']) && $validated['dia_pago'] > 0) {
                $cuota->update([
                    'dia_pago' => $validated['dia_pago'],
                    'dia_pago_nota' => $cuota->necesitaNotaExplicativa() ? $cuota->getNotaExplicativaAutomatica() : null
                ]);
            } else {
                // Si no se especifica día de pago, usar el día de la fecha de inicio
                $fechaInicio = $validated['fecha_inicio'] ?? now();
                $diaPago = Carbon::parse($fechaInicio)->day;

                $cuota->update([
                    'dia_pago' => $diaPago,
                    'dia_pago_nota' => $diaPago > 28 ? "En meses con menos de {$diaPago} días, el pago se realizará el último día del mes." : null
                ]);
            }
        }

        // Asignar cuota al socio
        $socio->update([
            'cuota_socio_id' => $cuota->id,
            'fecha_asignacion_cuota' => now()
        ]);

        // Generar períodos automáticos
        $cantidadPeriodos = $validated['cantidad_periodos'] ?? 12;
        $fechaInicio = $validated['fecha_inicio'] ?? null;

        $periodos = $this->periodoCuotaService->generarPeriodosParaSocio(
            $socio->id,
            $cuota->id,
            $cantidadPeriodos,
            $fechaInicio
        );

        return response()->json([
            'success' => true,
            'message' => 'Cuota asignada exitosamente',
            'data' => [
                'socio' => $socio->load('cuotaAsignada'),
                'periodos_generados' => count($periodos)
            ]
        ], 200);
    }

    /**
     * DELETE /admin/cuotas-socios/remover-cuota/{socioId}
     * Remover cuota de un socio
     */
    public function removerCuotaDeSocio($socioId)
    {
        $socio = BusinessRegistration::findOrFail($socioId);

        // Eliminar períodos pendientes
        PeriodoCuotaSocio::where('socio_id', $socioId)
            ->whereIn('estado', ['pendiente', 'en_revision'])
            ->delete();

        // Remover cuota
        $socio->update([
            'cuota_socio_id' => null,
            'fecha_asignacion_cuota' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cuota removida exitosamente'
        ]);
    }

    /**
     * GET /admin/cuotas-socios/periodos/{socioId}
     * Ver períodos de un socio
     */
    public function verPeriodosDeSocio($socioId)
    {
        // Auto-calcular periodos de tipo porcentaje que ya tienen datos
        $this->autoCalcularPeriodosPorcentaje($socioId);

        $periodos = $this->periodoCuotaService->obtenerPeriodosDeSocio($socioId);

        return response()->json([
            'success' => true,
            'data' => $periodos
        ]);
    }

    /**
     * Auto-calcula periodos de tipo porcentaje cuyo periodo_inicio ya pasó.
     * Solo para periodos pendientes o vencidos con cuota tipo porcentaje.
     */
    private function autoCalcularPeriodosPorcentaje($socioId)
    {
        try {
            $socio = BusinessRegistration::find($socioId);
            if (!$socio || !$socio->cuota_socio_id) return;

            $cuota = CuotaSocio::find($socio->cuota_socio_id);
            if (!$cuota || $cuota->tipo_cuota !== 'porcentaje') return;

            $hoy = Carbon::now()->startOfDay();
            $calculoService = app(CalculoCuotaService::class);

            // Obtener periodos que ya iniciaron y están pendientes/vencidos
            $periodos = PeriodoCuotaSocio::where('socio_id', $socioId)
                ->where('cuota_socio_id', $cuota->id)
                ->whereIn('estado', ['pendiente', 'vencido'])
                ->where('periodo_inicio', '<=', $hoy)
                ->get();

            foreach ($periodos as $periodo) {
                $calculoService->calcularCuotaDelPeriodo($periodo->id);
            }
        } catch (\Exception $e) {
            // No bloquear la consulta si falla el cálculo
            \Illuminate\Support\Facades\Log::warning("Auto-cálculo falló para socio {$socioId}: " . $e->getMessage());
        }
    }

    /**
     * GET /socio/mi-periodo-actual
     * Ver período actual del socio autenticado con información de si puede pagar
     */
    public function miPeriodoActual(Request $request)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        // Auto-calcular comisiones pendientes antes de consultar
        $this->autoCalcularPeriodosPorcentaje($socio->id);

        $periodo = $this->periodoCuotaService->obtenerPeriodoActualDeSocio($socio->id);

        if (!$periodo) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un período activo en este momento'
            ], 404);
        }

        // Determinar si puede pagar según el estado del período
        $puedePagar = false;
        $mensaje = null;

        switch ($periodo->estado) {
            case 'pendiente':
                $puedePagar = true;
                break;
            case 'vencido':
                $puedePagar = true;
                $mensaje = 'Este período está vencido. Por favor, realiza el pago lo antes posible.';
                break;
            case 'en_revision':
                $puedePagar = false;
                $mensaje = 'Ya subiste un comprobante para este período. Estamos revisando tu pago.';
                break;
            case 'pagado':
                $puedePagar = false;
                $mensaje = 'Este período ya está pagado.';
                break;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => $periodo,
                'puede_pagar' => $puedePagar,
                'mensaje' => $mensaje
            ]
        ]);
    }

    /**
     * GET /socio/mis-periodos
     * Ver todos los períodos del socio autenticado
     */
    public function misPeriodos(Request $request)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        // Auto-calcular comisiones pendientes antes de consultar
        $this->autoCalcularPeriodosPorcentaje($socio->id);

        $periodos = $this->periodoCuotaService->obtenerPeriodosDeSocio($socio->id);

        return response()->json([
            'success' => true,
            'data' => $periodos
        ]);
    }

    /**
     * GET /socio/pedidos-periodo/{periodoId}
     * Obtener los pedidos completados de un período específico del socio
     */
    public function pedidosPeriodo(Request $request, $periodoId)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        $periodo = PeriodoCuotaSocio::where('id', $periodoId)
            ->where('socio_id', $socio->id)
            ->first();

        if (!$periodo) {
            return response()->json([
                'success' => false,
                'message' => 'Período no encontrado'
            ], 404);
        }

        $cuota = CuotaSocio::find($periodo->cuota_socio_id);
        $porcentaje = $cuota && $cuota->tipo_cuota === 'porcentaje' ? (float)$cuota->porcentaje_comision : null;

        // Buscar pedidos completados en el rango del período
        $pedidos = Pedido::where('pedidos.id_local', $socio->id)
            ->whereBetween('pedidos.created_at', [
                Carbon::parse($periodo->periodo_inicio)->startOfDay(),
                Carbon::parse($periodo->periodo_fin)->endOfDay()
            ])
            ->whereHas('trackings', function($query) {
                $query->where('estado', 8);
            })
            ->leftJoin('clientes', 'clientes.id', '=', 'pedidos.id_cliente')
            ->select('pedidos.*', 'clientes.nombre as cliente_nombre', 'clientes.apellido as cliente_apellido')
            ->orderBy('pedidos.created_at', 'desc')
            ->get()
            ->map(function($pedido) use ($porcentaje) {
                // Usar pedidos.subtotal (ya tiene descuento aplicado), no la suma de detalles
                $subtotal = (float)$pedido->subtotal;
                $descuento = (float)($pedido->descuento ?? 0);
                $numProductos = PedidoDetalle::where('pedido_id', $pedido->id)->sum('cantidad')
                    ?: PedidoDetalle::where('pedido_id', $pedido->id)->count();
                $comision = $porcentaje ? round($subtotal * $porcentaje / 100, 2) : 0;
                return [
                    'id' => $pedido->id,
                    'codigo' => $pedido->codigo,
                    'cliente' => trim(($pedido->cliente_nombre ?? '') . ' ' . ($pedido->cliente_apellido ?? '')) ?: null,
                    'fecha' => $pedido->created_at->format('Y-m-d H:i'),
                    'subtotal' => round($subtotal, 2),
                    'descuento' => round($descuento, 2),
                    'comision' => $comision,
                    'neto' => round($subtotal - $comision, 2),
                    'num_productos' => (int)$numProductos,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'pedidos' => $pedidos,
                'porcentaje' => $porcentaje,
                'periodo_inicio' => $periodo->periodo_inicio,
                'periodo_fin' => $periodo->periodo_fin,
            ]
        ]);
    }

    /**
     * GET /socio/verificar-acceso
     * Verificar si el socio puede acceder al sistema
     */
    public function verificarAcceso(Request $request)
    {
        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        $resultado = $this->periodoCuotaService->verificarAccesoSocio($socio->id);

        return response()->json([
            'success' => true,
            'data' => $resultado
        ]);
    }

    /**
     * POST /socio/subir-comprobante-periodo
     * Subir comprobante para un período específico
     */
    public function subirComprobantePeriodo(Request $request)
    {
        $validated = $request->validate([
            'periodo_id' => 'required|exists:periodos_cuotas_socios,id',
            'comprobante_pago' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'fecha_pago' => 'required|date',
            'monto_pagado' => 'required|numeric|min:0',
            'metodo_pago' => 'required|string|in:yape,transferencia,deposito',
            'numero_operacion' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string'
        ]);

        $user = $request->user();
        $socio = $user->businessRegistration;

        if (!$socio) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no es un socio válido'
            ], 403);
        }

        $periodo = PeriodoCuotaSocio::findOrFail($validated['periodo_id']);

        // Verificar que el período pertenece al socio
        if ($periodo->socio_id !== $socio->id) {
            return response()->json([
                'success' => false,
                'message' => 'Este período no te pertenece'
            ], 403);
        }

        // Verificar que el período está pendiente
        if ($periodo->estado !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Este período ya no está disponible para pago'
            ], 400);
        }

        // Subir imagen del comprobante
        $path = null;
        if ($request->hasFile('comprobante_pago')) {
            $path = $request->file('comprobante_pago')->store('comprobantes-cuotas', 'custom_public');
        }

        // Crear pago
        $pago = PagoCuotaSocio::create([
            'cuota_socio_id' => $periodo->cuota_socio_id,
            'socio_id' => $socio->id,
            'comprobante_pago' => $path,
            'estado_pago' => 'pendiente',
            'fecha_pago' => $validated['fecha_pago'],
            'monto_pagado' => $validated['monto_pagado'],
            'metodo_pago' => $validated['metodo_pago'],
            'numero_operacion' => $validated['numero_operacion'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null
        ]);

        // Vincular pago al período
        $this->periodoCuotaService->vincularPagoAPeriodo($periodo->id, $pago->id);

        return response()->json([
            'success' => true,
            'message' => 'Comprobante subido exitosamente. En espera de aprobación.',
            'data' => $pago->load('cuota')
        ], 201);
    }

    /**
     * GET /admin/cuotas-socios/{id}
     * Obtener detalle de una cuota específica
     */
    public function show($id)
    {
        $cuota = CuotaSocio::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cuota
        ]);
    }

    /**
     * GET /admin/cuotas-socios/estado-pagos/{socio_id}
     * Obtener estado de pagos de un socio
     */
    public function getEstadoPagosSocio($socioId)
    {
        // Auto-calcular periodos de tipo porcentaje antes de obtener estadísticas
        $this->autoCalcularPeriodosPorcentaje($socioId);

        $socio = BusinessRegistration::findOrFail($socioId);

        // Verificar si tiene cuota asignada
        if (!$socio->cuota_socio_id) {
            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_cuota' => false,
                    'periodos_vencidos' => 0,
                    'periodos_pendientes' => 0,
                    'periodos_pagados' => 0,
                    'total_adeudado' => 0,
                    'esta_al_dia' => false,
                ]
            ]);
        }

        // Obtener la cuota
        $cuota = CuotaSocio::find($socio->cuota_socio_id);

        // Obtener estadísticas de períodos
        $hoy = Carbon::now()->startOfDay();
        $periodos = PeriodoCuotaSocio::where('socio_id', $socioId)->get();

        $periodosVencidos = $periodos->where('estado', 'vencido')->count();
        // Solo contar pendientes cuyo periodo ya inició (no futuros)
        $periodosPendientesActivos = $periodos->where('estado', 'pendiente')
            ->filter(function ($p) use ($hoy) {
                return Carbon::parse($p->periodo_inicio)->startOfDay()->lte($hoy);
            })->count();
        $periodosPagados = $periodos->where('estado', 'pagado')->count();

        // Calcular total adeudado (solo períodos vencidos)
        $totalAdeudado = $periodos->where('estado', 'vencido')->sum('monto_esperado');

        // Calcular días de vencimiento/por vencer
        $diasVencimiento = null;

        if ($periodosVencidos > 0) {
            // Días desde que venció el período más antiguo
            $periodoMasAntiguo = $periodos->where('estado', 'vencido')
                ->sortBy('fecha_vencimiento')
                ->first();
            if ($periodoMasAntiguo) {
                $diasVencimiento = (int) abs($hoy->diffInDays(
                    Carbon::parse($periodoMasAntiguo->fecha_vencimiento)->startOfDay(),
                    false
                ));
            }
        } elseif ($periodosPendientesActivos > 0) {
            // Días hasta que vence el período pendiente más próximo
            $periodoProximo = $periodos->where('estado', 'pendiente')
                ->filter(function ($p) use ($hoy) {
                    return Carbon::parse($p->periodo_inicio)->startOfDay()->lte($hoy);
                })
                ->sortBy('fecha_vencimiento')
                ->first();
            if ($periodoProximo) {
                $diasVencimiento = (int) Carbon::parse($periodoProximo->fecha_vencimiento)->startOfDay()->diffInDays($hoy, false);
            }
        }

        // Un socio está al día si no tiene períodos vencidos ni pendientes activos
        $estaAlDia = $periodosVencidos === 0 && $periodosPendientesActivos === 0;

        return response()->json([
            'success' => true,
            'data' => [
                'tiene_cuota' => true,
                'cuota' => $cuota,
                'periodos_vencidos' => $periodosVencidos,
                'periodos_pendientes' => $periodosPendientesActivos,
                'periodos_pagados' => $periodosPagados,
                'total_adeudado' => $totalAdeudado,
                'esta_al_dia' => $estaAlDia,
                'dias_vencimiento' => $diasVencimiento
            ]
        ]);
    }

    /**
     * PUT /admin/cuotas-socios/{id}/actualizar-dia-pago
     * Actualizar día de pago de una cuota específica
     */
    public function actualizarDiaPago(Request $request, $id)
    {
        $validated = $request->validate([
            'dia_pago' => 'required|integer|min:1|max:31',
            'dia_pago_nota' => 'nullable|string|max:500'
        ]);

        $cuota = CuotaSocio::findOrFail($id);

        // Actualizar día de pago
        $cuota->update([
            'dia_pago' => $validated['dia_pago'],
            'dia_pago_nota' => $validated['dia_pago_nota'] ?? 
                ($validated['dia_pago'] > 28 ? "En meses con menos de {$validated['dia_pago']} días, el pago se realizará el último día del mes." : null)
        ]);

        // Opcional: Recalcular períodos futuros pendientes si es necesario
        // Esto podría ser útil si quieres ajustar las fechas de vencimiento de períodos futuros
        $this->recalcularPeriodosFuturos($cuota->id, $validated['dia_pago']);

        return response()->json([
            'success' => true,
            'message' => 'Día de pago actualizado exitosamente',
            'data' => $cuota->fresh()
        ]);
    }

    /**
     * Método privado para recalcular períodos futuros cuando se cambia el día de pago
     */
    private function recalcularPeriodosFuturos($cuotaId, $nuevoDiaPago)
    {
        $cuota = CuotaSocio::find($cuotaId);
        // Obtener todos los socios que tienen esta cuota asignada
        $socios = BusinessRegistration::where('cuota_socio_id', $cuotaId)->get();

        foreach ($socios as $socio) {
            // Obtener períodos futuros pendientes o vencidos
            $periodosPendientes = PeriodoCuotaSocio::where('socio_id', $socio->id)
                ->where('cuota_socio_id', $cuotaId)
                ->whereIn('estado', ['pendiente', 'vencido'])
                ->get();

            foreach ($periodosPendientes as $periodo) {
                if ($cuota && $cuota->tipo_cuota === 'porcentaje') {
                    // Para porcentaje: usar periodo_fin como base para calcular vencimiento
                    // El vencimiento se calcula sobre el mes del fin del período
                    $fechaVencimiento = Carbon::parse($periodo->periodo_fin);
                    $fechaVencimiento->day = min($nuevoDiaPago, $fechaVencimiento->daysInMonth);
                } else {
                    // Para monto fijo: usar periodo_inicio como base
                    $fechaVencimiento = Carbon::parse($periodo->periodo_inicio);
                    $fechaVencimiento->day = min($nuevoDiaPago, $fechaVencimiento->daysInMonth);
                }

                $periodo->update([
                    'fecha_vencimiento' => $fechaVencimiento
                ]);
            }
        }
    }

    // ==================== NUEVOS ENDPOINTS PARA COMISIONES POR PORCENTAJE ====================

    /**
     * POST /admin/cuotas-socios/calcular-periodo/{periodoId}
     * Calcular manualmente la cuota/comisión de un período específico
     */
    public function calcularPeriodo($periodoId)
    {
        try {
            $calculoService = app(CalculoCuotaService::class);
            $periodo = $calculoService->calcularCuotaDelPeriodo($periodoId);

            return response()->json([
                'success' => true,
                'message' => 'Cuota calculada exitosamente',
                'data' => [
                    'periodo_id' => $periodo->id,
                    'total_ventas' => $periodo->total_ventas,
                    'cantidad_pedidos' => $periodo->cantidad_pedidos,
                    'monto_calculado' => $periodo->monto_calculado,
                    'monto_esperado' => $periodo->monto_esperado,
                    'fecha_calculo' => $periodo->fecha_calculo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular la cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /admin/cuotas-socios/calcular-socio/{socioId}
     * Calcular todas las cuotas pendientes de un socio
     */
    public function calcularCuotasSocio($socioId)
    {
        try {
            $calculoService = app(CalculoCuotaService::class);
            $resultados = $calculoService->calcularTodasLasCuotasPendientes($socioId);

            $exitosos = collect($resultados)->where('success', true)->count();
            $fallidos = collect($resultados)->where('success', false)->count();

            return response()->json([
                'success' => true,
                'message' => "Cálculo completado: {$exitosos} exitosos, {$fallidos} fallidos",
                'data' => $resultados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular las cuotas del socio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /admin/cuotas-socios/calcular-todos/{cuotaId}
     * Calcular cuotas para todos los socios de una cuota específica
     */
    public function calcularTodasLasCuotas($cuotaId)
    {
        try {
            $calculoService = app(CalculoCuotaService::class);
            $resultados = $calculoService->calcularCuotasParaTodosLosSocios($cuotaId);

            return response()->json([
                'success' => true,
                'message' => 'Cálculo masivo completado',
                'data' => $resultados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular las cuotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/cuotas-socios/detalle-periodo/{periodoId}
     * Obtener detalle completo de un período con cálculos
     */
    public function detallePeriodo($periodoId)
    {
        try {
            $periodo = PeriodoCuotaSocio::with(['cuota', 'socio', 'pago'])->findOrFail($periodoId);
            $calculoService = app(CalculoCuotaService::class);

            // Obtener ventas y pedidos del período
            $resultado = $calculoService->calcularVentasYPedidos(
                $periodo->socio_id,
                $periodo->periodo_inicio,
                $periodo->periodo_fin
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $periodo,
                    'detalle_calculo' => [
                        'total_ventas' => $resultado['total_ventas'],
                        'cantidad_pedidos' => $resultado['cantidad_pedidos'],
                        'tipo_cuota' => $periodo->cuota->tipo_cuota,
                        'porcentaje_comision' => $periodo->cuota->porcentaje_comision,
                        'minimo_pedidos' => $periodo->cuota->minimo_pedidos,
                        'exonerar_si_menos_pedidos' => $periodo->cuota->exonerar_si_menos_pedidos,
                        'monto_minimo' => $periodo->cuota->monto_minimo,
                        'monto_calculado' => $periodo->monto_calculado,
                        'monto_esperado' => $periodo->monto_esperado
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del período',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}