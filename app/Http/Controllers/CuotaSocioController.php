<?php

namespace App\Http\Controllers;

use App\Models\CuotaSocio;
use App\Models\PagoCuotaSocio;
use App\Models\PeriodoCuotaSocio;
use App\Models\BusinessRegistration;
use App\Services\PeriodoCuotaService;
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
            'monto_cuota' => 'required|numeric|min:0',
            'numero_cuenta' => 'required|string|max:50',
            'tipo_cuenta' => 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'metodos_pago_disponibles' => 'nullable|array',
            'descripcion' => 'nullable|string'
        ]);

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
            'monto_cuota' => 'sometimes|numeric|min:0',
            'numero_cuenta' => 'sometimes|string|max:50',
            'tipo_cuenta' => 'nullable|string|max:50',
            'banco' => 'nullable|string|max:100',
            'metodos_pago_disponibles' => 'nullable|array',
            'estado' => 'sometimes|in:activo,inactivo',
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
            'fecha_inicio' => 'nullable|date' // Fecha de inicio personalizada
        ]);

        $socio = BusinessRegistration::findOrFail($validated['socio_id']);
        $cuota = CuotaSocio::findOrFail($validated['cuota_socio_id']);

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
        $periodos = $this->periodoCuotaService->obtenerPeriodosDeSocio($socioId);

        return response()->json([
            'success' => true,
            'data' => $periodos
        ]);
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

        $periodos = $this->periodoCuotaService->obtenerPeriodosDeSocio($socio->id);

        return response()->json([
            'success' => true,
            'data' => $periodos
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
        $periodos = PeriodoCuotaSocio::where('socio_id', $socioId)->get();

        $periodosVencidos = $periodos->where('estado', 'vencido')->count();
        $periodosPendientes = $periodos->where('estado', 'pendiente')->count();
        $periodosPagados = $periodos->where('estado', 'pagado')->count();

        // Calcular total adeudado (solo períodos vencidos)
        $totalAdeudado = $periodos->where('estado', 'vencido')->sum('monto_esperado');

        // Calcular días de vencimiento (del período más antiguo vencido)
        $diasVencimiento = null;
        $periodoMasAntiguo = $periodos->where('estado', 'vencido')
            ->sortBy('fecha_vencimiento')
            ->first();

        if ($periodoMasAntiguo) {
            $diasVencimiento = (int) abs(Carbon::now()->startOfDay()->diffInDays(
                Carbon::parse($periodoMasAntiguo->fecha_vencimiento)->startOfDay(),
                false
            ));
        }

        // Un socio está al día solo si NO tiene períodos vencidos NI pendientes
        // Es decir, todos sus períodos están pagados
        $estaAlDia = $periodosVencidos === 0 && $periodosPendientes === 0;

        return response()->json([
            'success' => true,
            'data' => [
                'tiene_cuota' => true,
                'cuota' => $cuota,
                'periodos_vencidos' => $periodosVencidos,
                'periodos_pendientes' => $periodosPendientes,
                'periodos_pagados' => $periodosPagados,
                'total_adeudado' => $totalAdeudado,
                'esta_al_dia' => $estaAlDia,
                'dias_vencimiento' => $diasVencimiento
            ]
        ]);
    }
}