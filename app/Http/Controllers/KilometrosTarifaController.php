<?php

namespace App\Http\Controllers;

use App\Models\KilometrosTarifa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class KilometrosTarifaController extends Controller
{
    /**
     * Listar todas las configuraciones de tarifas
     */
    public function index(): JsonResponse
    {
        try {
            $tarifas = KilometrosTarifa::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $tarifas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las tarifas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una configuración específica
     */
    public function show($id): JsonResponse
    {
        try {
            $tarifa = KilometrosTarifa::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tarifa
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva configuración de tarifa
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'precio_base_diurno' => 'required|numeric|min:0',
                'precio_base_nocturno' => 'required|numeric|min:0',
                'precio_por_km_diurno' => 'required|numeric|min:0',
                'precio_por_km_nocturno' => 'required|numeric|min:0',
                'precio_maximo' => 'nullable|numeric|min:0',
                'distancia_maxima' => 'nullable|numeric|min:0',
                'distancia_minima' => 'nullable|numeric|min:0',
                'nombre' => 'required|string|max:100',
                'descripcion' => 'nullable|string',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si se marca como activo, desactivar solo las demás del mismo ámbito
            if ($request->get('activo', false)) {
                $query = KilometrosTarifa::where('activo', true);
                if ($request->has('business_registration_id') && $request->business_registration_id) {
                    $query->where('business_registration_id', $request->business_registration_id);
                } else {
                    $query->whereNull('business_registration_id');
                }
                $query->update(['activo' => false]);
            }

            $tarifa = KilometrosTarifa::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Configuración de tarifa creada exitosamente',
                'data' => $tarifa
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar configuración de tarifa
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $tarifa = KilometrosTarifa::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'precio_base_diurno' => 'required|numeric|min:0',
                'precio_base_nocturno' => 'required|numeric|min:0',
                'precio_por_km_diurno' => 'nullable|numeric|min:0',
                'precio_por_km_nocturno' => 'nullable|numeric|min:0',
                'precio_maximo' => 'nullable|numeric|min:0',
                'distancia_maxima' => 'nullable|numeric|min:0',
                'distancia_minima' => 'nullable|numeric|min:0',
                'nombre' => 'nullable|string|max:100',
                'descripcion' => 'nullable|string',
                'activo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si se marca como activo, desactivar solo las demás del mismo ámbito
            if ($request->get('activo', false)) {
                $query = KilometrosTarifa::where('id', '!=', $id)
                    ->where('activo', true);
                if ($tarifa->business_registration_id) {
                    $query->where('business_registration_id', $tarifa->business_registration_id);
                } else {
                    $query->whereNull('business_registration_id');
                }
                $query->update(['activo' => false]);
            }

            $tarifa->update($request->only([
                'precio_base_diurno',
                'precio_base_nocturno',
                'precio_maximo',
                'distancia_maxima',
                'distancia_minima',
                'nombre',
                'descripcion',
                'activo',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => $tarifa->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar configuración de tarifa
     */
    public function destroy($id): JsonResponse
    {
        try {
            $tarifa = KilometrosTarifa::findOrFail($id);

            // No permitir eliminar si es la única configuración activa
            if ($tarifa->activo && KilometrosTarifa::where('activo', true)->count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la única configuración activa'
                ], 400);
            }

            $tarifa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Configuración eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar una configuración específica
     */
    public function activar($id): JsonResponse
    {
        try {
            $tarifa = KilometrosTarifa::findOrFail($id);
            $tarifa->activar();

            return response()->json([
                'success' => true,
                'message' => 'Configuración activada exitosamente',
                'data' => $tarifa->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al activar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la configuración activa actual
     */
    public function getActiva(): JsonResponse
    {
        try {
            $tarifa = KilometrosTarifa::getConfiguracionActiva();

            if (!$tarifa) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay configuración activa'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $tarifa
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración activa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}