<?php

namespace App\Http\Controllers;

use App\Models\KilometrosTarifa;
use App\Models\TarifaRango;
use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\ClienteDireccion;
use App\Services\PedidoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TarifaRangoController extends Controller
{
    protected $pedidoService;

    public function __construct(PedidoService $pedidoService)
    {
        $this->pedidoService = $pedidoService;
    }

    /**
     * Obtener la configuración activa con sus rangos
     * SIMPLIFICADO: Solo hay UNA configuración (kilometros_tarifa id=1)
     */
    public function index(): JsonResponse
    {
        try {
            $config = KilometrosTarifa::with('rangos')->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay configuración de tarifas'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la configuración activa (alias de index para compatibilidad)
     */
    public function getActiva(): JsonResponse
    {
        return $this->index();
    }

    /**
     * Mostrar una configuración específica (para compatibilidad)
     * Como solo hay UNA configuración, siempre devuelve la del id=1
     */
    public function show($id): JsonResponse
    {
        return $this->index();
    }

    /**
     * Crear/Actualizar configuración (para compatibilidad con frontend)
     * Como solo hay UNA configuración, siempre actualiza el id=1
     */
    public function store(Request $request): JsonResponse
    {
        // Redirigir a update con id=1
        return $this->update($request, 1);
    }

    /**
     * Eliminar configuración (para compatibilidad)
     * No se puede eliminar la única configuración, retorna error
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No se puede eliminar la configuración principal del sistema'
        ], 400);
    }

    /**
     * Activar configuración (para compatibilidad)
     * Como solo hay UNA configuración, siempre está activa
     */
    public function activar($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'La configuración ya está activa',
            'data' => KilometrosTarifa::with('rangos')->first()
        ]);
    }

    /**
     * Actualizar configuración existente (siempre el id=1)
     */
    public function update(Request $request, $id = 1): JsonResponse
    {
        try {
            $config = KilometrosTarifa::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nombre' => 'nullable|string|max:100',
                'descripcion' => 'nullable|string',
                'hora_inicio_nocturno' => 'required|date_format:H:i:s',
                'hora_fin_nocturno' => 'required|date_format:H:i:s',
                'activo' => 'nullable|boolean',
                'rangos' => 'required|array|min:1',
                'rangos.*.distancia_desde' => 'required|numeric|min:0',
                'rangos.*.distancia_hasta' => 'nullable|numeric|min:0',
                'rangos.*.precio_diurno' => 'required|numeric|min:0',
                'rangos.*.precio_nocturno' => 'required|numeric|min:0',
                'rangos.*.orden' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Actualizar configuración
            $config->update([
                'hora_inicio_nocturno' => $request->hora_inicio_nocturno,
                'hora_fin_nocturno' => $request->hora_fin_nocturno,
            ]);

            // Eliminar rangos antiguos
            TarifaRango::where('kilometros_tarifa_id', $config->id)->delete();

            // Crear nuevos rangos con orden automático
            $orden = 1;
            foreach ($request->rangos as $rangoData) {
                TarifaRango::create([
                    'kilometros_tarifa_id' => $config->id,
                    'distancia_desde' => $rangoData['distancia_desde'],
                    'distancia_hasta' => $rangoData['distancia_hasta'],
                    'precio_diurno' => $rangoData['precio_diurno'],
                    'precio_nocturno' => $rangoData['precio_nocturno'],
                    'orden' => isset($rangoData['orden']) ? $rangoData['orden'] : $orden++
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración actualizada exitosamente',
                'data' => $config->fresh()->load('rangos')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la configuración',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CALCULADORA: Calcular precio para un local y cliente específico
     * Esta es la función que el admin usa DESPUÉS de guardar para probar
     */
    public function calcularPreview(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_local' => 'required|integer|exists:business_registrations,id',
                'id_cliente' => 'required|integer|exists:clientes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener coordenadas del local
            $local = Establecimiento::where('business_registration_id', $request->id_local)->first();
            if (!$local) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron coordenadas para el local'
                ], 404);
            }

            // Obtener dirección del cliente
            $clienteDireccion = ClienteDireccion::where('id_cliente', $request->id_cliente)->first();
            if (!$clienteDireccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró dirección para el cliente'
                ], 404);
            }

            $coordenadas = json_decode($clienteDireccion->coordenadas);

            // Normalizar coordenadas
            $lat1 = round((float) $local->latitud, 6);
            $lon1 = round((float) $local->longitud, 6);
            $lat2 = round((float) $coordenadas->coordinates[1], 6);
            $lon2 = round((float) $coordenadas->coordinates[0], 6);

            // Calcular distancia usando Mapbox
            $distanciaKm = $this->pedidoService->obtenerDistancia($lat1, $lon1, $lat2, $lon2);

            if (!$distanciaKm) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo calcular la distancia'
                ], 500);
            }

            // Obtener configuración activa
            $config = KilometrosTarifa::first();
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay configuración de tarifas activa'
                ], 404);
            }

            // Buscar el rango que aplica
            $rango = TarifaRango::where('kilometros_tarifa_id', $config->id)
                ->where('distancia_desde', '<=', $distanciaKm)
                ->where(function($query) use ($distanciaKm) {
                    $query->where('distancia_hasta', '>=', $distanciaKm)
                          ->orWhereNull('distancia_hasta');
                })
                ->orderBy('orden')
                ->first();

            if (!$rango) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un rango de tarifa para esta distancia'
                ], 404);
            }

            // Obtener info del local y cliente para mostrar
            $negocio = BusinessRegistration::find($request->id_local);
            $cliente = Cliente::find($request->id_cliente);

            return response()->json([
                'success' => true,
                'data' => [
                    'local' => [
                        'id' => $negocio->id,
                        'nombre' => $negocio->name ?? 'Sin nombre',
                        'direccion' => $local->direccion_completa
                    ],
                    'cliente' => [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                        'direccion' => $clienteDireccion->direccion
                    ],
                    'distancia_km' => round($distanciaKm, 2),
                    'rango_aplicado' => [
                        'distancia_desde' => number_format($rango->distancia_desde, 2, '.', ''),
                        'distancia_hasta' => $rango->distancia_hasta ? number_format($rango->distancia_hasta, 2, '.', '') : null,
                        'precio_diurno' => number_format($rango->precio_diurno, 2, '.', ''),
                        'precio_nocturno' => number_format($rango->precio_nocturno, 2, '.', '')
                    ],
                    'precio_diurno' => number_format($rango->precio_diurno, 2, '.', ''),
                    'precio_nocturno' => number_format($rango->precio_nocturno, 2, '.', ''),
                    'horario_nocturno' => [
                        'inicio' => substr($config->hora_inicio_nocturno, 0, 5),
                        'fin' => substr($config->hora_fin_nocturno, 0, 5)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular el precio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de locales para el select
     */
    public function getLocales(): JsonResponse
    {
        try {
            $locales = Establecimiento::select(
                    'establecimientos.id',
                    'establecimientos.nombre_establecimiento',
                    'establecimientos.latitud',
                    'establecimientos.longitud',
                    'establecimientos.business_registration_id'
                )
                ->join('business_registrations', 'establecimientos.business_registration_id', '=', 'business_registrations.id')
                ->where('business_registrations.aprobado', 1)
                ->where('business_registrations.activo', 1)
                ->whereNotNull('establecimientos.latitud')
                ->whereNotNull('establecimientos.longitud')
                ->orderBy('establecimientos.nombre_establecimiento')
                ->get()
                ->map(function($local) {
                    return [
                        'id' => $local->business_registration_id, // Enviar business_registration_id
                        'nombre' => $local->nombre_establecimiento,
                        'coordenadas' => $local->latitud . ',' . $local->longitud
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $locales
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener locales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de clientes para el select
     */
    public function getClientes(): JsonResponse
    {
        try {
            $clientes = Cliente::select('clientes.id', 'clientes.nombre', 'clientes.apellido')
                ->join('clientes_direcciones', 'clientes.id', '=', 'clientes_direcciones.id_cliente')
                ->whereNotNull('clientes_direcciones.direccion')
                ->orderBy('clientes.nombre')
                ->get()
                ->map(function($cliente) {
                    $direccion = \DB::table('clientes_direcciones')
                        ->where('id_cliente', $cliente->id)
                        ->first();
                    
                    return [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                        'direccion' => $direccion ? $direccion->direccion : null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
