<?php

namespace App\Http\Controllers;

use App\Models\CuotaMotorizado;
use App\Models\ConfiguracionCuota;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CuotaMotorizadoController extends Controller
{
    // Obtener todas las cuotas con filtros
    public function index(Request $request)
    {
        try {
            $query = CuotaMotorizado::with(['motorizado']);

            // Filtros
            if ($request->has('estado_pago') && $request->estado_pago !== 'todos') {
                if ($request->estado_pago === 'vencidas') {
                    $query->vencidas();
                } else {
                    $query->where('estado_pago', $request->estado_pago);
                }
            }

            if ($request->has('motorizado_id')) {
                $query->where('motorizado_id', $request->motorizado_id);
            }

            if ($request->has('semana_inicio')) {
                $query->where('semana_inicio', '>=', $request->semana_inicio);
            }

            if ($request->has('semana_fin')) {
                $query->where('semana_fin', '<=', $request->semana_fin);
            }

            $cuotas = $query->orderBy('semana_inicio', 'desc')
                           ->orderBy('fecha_vencimiento', 'asc')
                           ->get();

            return response()->json([
                'status' => 'success',
                'data' => $cuotas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener cuotas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las cuotas'
            ], 500);
        }
    }

    // Crear cuotas para la semana actual
    public function generarCuotasSemanal(Request $request)
    {
        $request->validate([
            'monto_cuota' => 'required|numeric|min:0',
            'fecha_vencimiento' => 'required|date',
            'motorizado_ids' => 'array',
            'motorizado_ids.*' => 'exists:reparto_registros,id'
        ]);

        DB::beginTransaction();
        try {
            $inicioSemana = Carbon::now()->startOfWeek();
            $finSemana = Carbon::now()->endOfWeek();

            // Si no se especifican motorizados, usar todos los aprobados
            if (empty($request->motorizado_ids)) {
                $motorizados = RepartoRegistro::where('aprobado', true)
                                             ->where('estado', true)
                                             ->get();
            } else {
                $motorizados = RepartoRegistro::whereIn('id', $request->motorizado_ids)
                                             ->where('aprobado', true)
                                             ->get();
            }

            $cuotasCreadas = 0;
            $cuotasExistentes = 0;

            foreach ($motorizados as $motorizado) {
                // Verificar si ya existe una cuota para esta semana
                $cuotaExistente = CuotaMotorizado::where('motorizado_id', $motorizado->id)
                                                ->where('semana_inicio', $inicioSemana)
                                                ->where('semana_fin', $finSemana)
                                                ->first();

                if (!$cuotaExistente) {
                    CuotaMotorizado::create([
                        'motorizado_id' => $motorizado->id,
                        'semana_inicio' => $inicioSemana,
                        'semana_fin' => $finSemana,
                        'monto_cuota' => $request->monto_cuota,
                        'fecha_vencimiento' => $request->fecha_vencimiento,
                        'estado_pago' => 'pendiente'
                    ]);
                    $cuotasCreadas++;
                } else {
                    $cuotasExistentes++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Cuotas generadas: {$cuotasCreadas}. Ya existían: {$cuotasExistentes}",
                'data' => [
                    'creadas' => $cuotasCreadas,
                    'existentes' => $cuotasExistentes
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al generar cuotas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al generar las cuotas: ' . $e->getMessage()
            ], 500);
        }
    }

    // Marcar cuota como pagada
    public function marcarPagada(Request $request, $id)
    {
        $request->validate([
            'metodo_pago' => 'required|string|max:50',
            'observaciones' => 'nullable|string',
            'comprobante_pago' => 'nullable|string'
        ]);

        try {
            $cuota = CuotaMotorizado::findOrFail($id);

            $cuota->update([
                'estado_pago' => 'pagado',
                'fecha_pago' => Carbon::now(),
                'metodo_pago' => $request->metodo_pago,
                'observaciones' => $request->observaciones,
                'comprobante_pago' => $request->comprobante_pago
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cuota marcada como pagada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar cuota como pagada: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estado de la cuota'
            ], 500);
        }
    }

    // Revertir pago
    public function revertirPago($id)
    {
        try {
            $cuota = CuotaMotorizado::findOrFail($id);

            $cuota->update([
                'estado_pago' => 'pendiente',
                'fecha_pago' => null,
                'metodo_pago' => null,
                'observaciones' => null,
                'comprobante_pago' => null
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Pago revertido correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al revertir pago: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al revertir el pago'
            ], 500);
        }
    }

    // Obtener estadísticas
    public function estadisticas(Request $request)
    {
        try {
            $fechaInicio = $request->fecha_inicio ?? Carbon::now()->startOfMonth();
            $fechaFin = $request->fecha_fin ?? Carbon::now()->endOfMonth();

            $estadisticas = [
                'total_cuotas' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])->count(),
                'cuotas_pagadas' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                                  ->where('estado_pago', 'pagado')->count(),
                'cuotas_pendientes' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                                     ->where('estado_pago', 'pendiente')->count(),
                'cuotas_vencidas' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                                   ->vencidas()->count(),
                'monto_total' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                               ->sum('monto_cuota'),
                'monto_cobrado' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                                 ->where('estado_pago', 'pagado')
                                                 ->sum('monto_cuota'),
                'monto_pendiente' => CuotaMotorizado::whereBetween('semana_inicio', [$fechaInicio, $fechaFin])
                                                   ->where('estado_pago', '!=', 'pagado')
                                                   ->sum('monto_cuota')
            ];

            return response()->json([
                'status' => 'success',
                'data' => $estadisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las estadísticas'
            ], 500);
        }
    }

    // Obtener motorizados aprobados para asignar cuotas
    public function getMotorizadosAprobados()
    {
        try {
            $motorizados = RepartoRegistro::where('aprobado', true)
                                         ->where('estado', true)
                                         ->select('id', 'nombres', 'apellidos', 'email', 'celular')
                                         ->orderBy('nombres')
                                         ->get();

            return response()->json([
                'status' => 'success',
                'data' => $motorizados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener motorizados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los motorizados'
            ], 500);
        }
    }

    // Actualizar cuota individual
    public function update(Request $request, $id)
    {
        $request->validate([
            'monto_cuota' => 'required|numeric|min:0',
            'fecha_vencimiento' => 'required|date',
            'observaciones' => 'nullable|string'
        ]);

        try {
            $cuota = CuotaMotorizado::findOrFail($id);

            $cuota->update([
                'monto_cuota' => $request->monto_cuota,
                'fecha_vencimiento' => $request->fecha_vencimiento,
                'observaciones' => $request->observaciones
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cuota actualizada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar cuota: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la cuota'
            ], 500);
        }
    }

    // Eliminar cuota
    public function destroy($id)
    {
        try {
            $cuota = CuotaMotorizado::findOrFail($id);
            $cuota->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Cuota eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar cuota: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar la cuota'
            ], 500);
        }
    }
}