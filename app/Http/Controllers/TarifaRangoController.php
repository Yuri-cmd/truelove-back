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
     * Obtener la configuración GLOBAL (sin local asignado)
     */
    public function index(): JsonResponse
    {
        try {
            $config = KilometrosTarifa::with('rangos')
                ->whereNull('business_registration_id')
                ->first();

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
     * Alias de index
     */
    public function getActiva(): JsonResponse
    {
        return $this->index();
    }

    /**
     * Compatibilidad: devuelve siempre la global
     */
    public function show($id): JsonResponse
    {
        return $this->index();
    }

    /**
     * Crear/Actualizar config global (para compatibilidad con frontend)
     */
    public function store(Request $request): JsonResponse
    {
        return $this->update($request, 1);
    }

    /**
     * No se puede eliminar la config principal
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No se puede eliminar la configuración principal del sistema'
        ], 400);
    }

    /**
     * Activar config (compatibilidad)
     */
    public function activar($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'La configuración ya está activa',
            'data' => KilometrosTarifa::with('rangos')->whereNull('business_registration_id')->first()
        ]);
    }

    /**
     * Actualizar la configuración global (id=1)
     */
    public function update(Request $request, $id = 1): JsonResponse
    {
        try {
            $config = KilometrosTarifa::findOrFail($id);

            $rules = [
                'nombre' => 'nullable|string|max:100',
                'descripcion' => 'nullable|string',
                'hora_inicio_nocturno' => 'required|date_format:H:i:s',
                'hora_fin_nocturno' => 'required|date_format:H:i:s',
                'activo' => 'nullable|boolean',
                'modo_tarifa' => 'nullable|in:rangos,precio_por_km',
            ];

            $modeTarifa = $request->input('modo_tarifa', $config->modo_tarifa ?? 'rangos');

            if ($modeTarifa === 'rangos') {
                $rules['rangos'] = 'required|array|min:1';
                $rules['rangos.*.distancia_desde'] = 'required|numeric|min:0';
                $rules['rangos.*.distancia_hasta'] = 'nullable|numeric|min:0';
                $rules['rangos.*.precio_diurno'] = 'required|numeric|min:0';
                $rules['rangos.*.precio_nocturno'] = 'required|numeric|min:0';
            } else {
                $rules['precio_base_diurno'] = 'required|numeric|min:0';
                $rules['precio_base_nocturno'] = 'required|numeric|min:0';
                $rules['precio_por_km_diurno'] = 'required|numeric|min:0';
                $rules['precio_por_km_nocturno'] = 'required|numeric|min:0';
                $rules['precio_maximo'] = 'nullable|numeric|min:0';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [
                'hora_inicio_nocturno' => $request->hora_inicio_nocturno,
                'hora_fin_nocturno' => $request->hora_fin_nocturno,
                'modo_tarifa' => $modeTarifa,
            ];

            if ($modeTarifa === 'precio_por_km') {
                $updateData['precio_base_diurno'] = $request->precio_base_diurno;
                $updateData['precio_base_nocturno'] = $request->precio_base_nocturno;
                $updateData['precio_por_km_diurno'] = $request->precio_por_km_diurno;
                $updateData['precio_por_km_nocturno'] = $request->precio_por_km_nocturno;
                $updateData['precio_maximo'] = $request->precio_maximo;
            }

            $config->update($updateData);

            if ($modeTarifa === 'rangos' && $request->has('rangos')) {
                TarifaRango::where('kilometros_tarifa_id', $config->id)->delete();
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
     * NUEVO: Obtener todos los locales activos con su config propia (si tienen)
     */
    public function getLocalesConConfig(): JsonResponse
    {
        try {
            $locales = Establecimiento::select(
                    'establecimientos.business_registration_id',
                    'establecimientos.nombre_establecimiento'
                )
                ->join('business_registrations', 'establecimientos.business_registration_id', '=', 'business_registrations.id')
                ->where('business_registrations.aprobado', 1)
                ->whereNotNull('establecimientos.latitud')
                ->whereNotNull('establecimientos.longitud')
                ->orderBy('establecimientos.nombre_establecimiento')
                ->get()
                ->map(function($local) {
                    $config = KilometrosTarifa::with('rangos')
                        ->where('business_registration_id', $local->business_registration_id)
                        ->where('activo', true)
                        ->first();

                    return [
                        'id' => $local->business_registration_id,
                        'nombre' => $local->nombre_establecimiento,
                        'tiene_config_propia' => !is_null($config),
                        'config' => $config,
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
     * NUEVO: Obtener config de un local específico (o null si usa global)
     */
    public function getConfigLocal($idLocal): JsonResponse
    {
        try {
            $config = KilometrosTarifa::with('rangos')
                ->where('business_registration_id', $idLocal)
                ->where('activo', true)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $config,
                'usa_global' => is_null($config),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener config del local',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NUEVO: Guardar config personalizada para un local
     * Si ya tiene config, la actualiza. Si no, crea una nueva.
     */
    public function saveConfigLocal(Request $request, $idLocal): JsonResponse
    {
        try {
            $modeTarifa = $request->input('modo_tarifa', 'rangos');

            $rules = [
                'hora_inicio_nocturno' => 'required|date_format:H:i:s',
                'hora_fin_nocturno' => 'required|date_format:H:i:s',
                'modo_tarifa' => 'required|in:rangos,precio_por_km',
            ];

            if ($modeTarifa === 'rangos') {
                $rules['rangos'] = 'required|array|min:1';
                $rules['rangos.*.distancia_desde'] = 'required|numeric|min:0';
                $rules['rangos.*.distancia_hasta'] = 'nullable|numeric|min:0';
                $rules['rangos.*.precio_diurno'] = 'required|numeric|min:0';
                $rules['rangos.*.precio_nocturno'] = 'required|numeric|min:0';
            } else {
                $rules['precio_base_diurno'] = 'required|numeric|min:0';
                $rules['precio_base_nocturno'] = 'required|numeric|min:0';
                $rules['precio_por_km_diurno'] = 'required|numeric|min:0';
                $rules['precio_por_km_nocturno'] = 'required|numeric|min:0';
                $rules['precio_maximo'] = 'nullable|numeric|min:0';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Buscar config existente del local
            $config = KilometrosTarifa::where('business_registration_id', $idLocal)->first();

            $configData = [
                'nombre' => 'Tarifa - Local ' . $idLocal,
                'hora_inicio_nocturno' => $request->hora_inicio_nocturno,
                'hora_fin_nocturno' => $request->hora_fin_nocturno,
                'modo_tarifa' => $modeTarifa,
                'activo' => true,
                'business_registration_id' => $idLocal,
            ];

            if ($modeTarifa === 'precio_por_km') {
                $configData['precio_base_diurno'] = $request->precio_base_diurno;
                $configData['precio_base_nocturno'] = $request->precio_base_nocturno;
                $configData['precio_por_km_diurno'] = $request->precio_por_km_diurno;
                $configData['precio_por_km_nocturno'] = $request->precio_por_km_nocturno;
                $configData['precio_maximo'] = $request->precio_maximo;
            }

            if ($config) {
                $config->update($configData);
            } else {
                $config = KilometrosTarifa::create($configData);
            }

            if ($modeTarifa === 'rangos') {
                TarifaRango::where('kilometros_tarifa_id', $config->id)->delete();
                $orden = 1;
                foreach ($request->rangos as $rangoData) {
                    TarifaRango::create([
                        'kilometros_tarifa_id' => $config->id,
                        'distancia_desde' => $rangoData['distancia_desde'],
                        'distancia_hasta' => $rangoData['distancia_hasta'],
                        'precio_diurno' => $rangoData['precio_diurno'],
                        'precio_nocturno' => $rangoData['precio_nocturno'],
                        'orden' => $orden++
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración del local guardada exitosamente',
                'data' => $config->fresh()->load('rangos')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar config del local',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NUEVO: Eliminar config personalizada de un local (vuelve a usar la global)
     */
    public function deleteConfigLocal($idLocal): JsonResponse
    {
        try {
            $config = KilometrosTarifa::where('business_registration_id', $idLocal)->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'El local no tiene configuración propia'
                ], 404);
            }

            DB::beginTransaction();
            TarifaRango::where('kilometros_tarifa_id', $config->id)->delete();
            $config->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuración eliminada. El local ahora usa la tarifa global.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar config del local',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CALCULADORA: Calcular precio para un local y cliente específico
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

            $local = Establecimiento::where('business_registration_id', $request->id_local)->first();
            if (!$local) {
                return response()->json(['success' => false, 'message' => 'No se encontraron coordenadas para el local'], 404);
            }

            $clienteDireccion = ClienteDireccion::where('id_cliente', $request->id_cliente)->first();
            if (!$clienteDireccion) {
                return response()->json(['success' => false, 'message' => 'No se encontró dirección para el cliente'], 404);
            }

            $coordenadas = json_decode($clienteDireccion->coordenadas);
            $lat1 = round((float) $local->latitud, 6);
            $lon1 = round((float) $local->longitud, 6);
            $lat2 = round((float) $coordenadas->coordinates[1], 6);
            $lon2 = round((float) $coordenadas->coordinates[0], 6);

            // TODO: revisar si conviene volver a Mapbox (ruta real) — comentado 2026-02-17
            // $distanciaKm = $this->pedidoService->obtenerDistancia($lat1, $lon1, $lat2, $lon2);
            $distanciaKm = $this->pedidoService->calcularDistanciaHaversine($lat1, $lon1, $lat2, $lon2);

            if (!$distanciaKm) {
                return response()->json(['success' => false, 'message' => 'No se pudo calcular la distancia'], 500);
            }

            $precio = $this->pedidoService->calcularPrecioPorDistancia($distanciaKm, $request->id_local);

            $negocio = BusinessRegistration::find($request->id_local);
            $cliente = Cliente::find($request->id_cliente);

            // Determinar si tiene config propia o usa global
            $configPropia = KilometrosTarifa::where('business_registration_id', $request->id_local)->where('activo', true)->first();
            $tieneConfigPropia = !is_null($configPropia);

            // Para mostrar datos en el simulador
            $rangoAplicado = null;
            $modoTarifa = 'global';
            $configNombre = 'Configuración Global';
            $horarioNocturno = null;

            if ($tieneConfigPropia) {
                $modoTarifa = $configPropia->modo_tarifa;
                $configNombre = $configPropia->nombre;
                $horarioNocturno = [
                    'inicio' => substr($configPropia->hora_inicio_nocturno, 0, 5),
                    'fin' => substr($configPropia->hora_fin_nocturno, 0, 5),
                ];
                if ($configPropia->modo_tarifa === 'rangos') {
                    $rango = TarifaRango::where('kilometros_tarifa_id', $configPropia->id)
                        ->where('distancia_desde', '<=', $distanciaKm)
                        ->where(function($q) use ($distanciaKm) {
                            $q->where('distancia_hasta', '>=', $distanciaKm)->orWhereNull('distancia_hasta');
                        })
                        ->orderBy('orden')->first();
                    if ($rango) {
                        $rangoAplicado = [
                            'distancia_desde' => number_format($rango->distancia_desde, 2, '.', ''),
                            'distancia_hasta' => $rango->distancia_hasta ? number_format($rango->distancia_hasta, 2, '.', '') : null,
                            'precio_diurno' => number_format($rango->precio_diurno, 2, '.', ''),
                            'precio_nocturno' => number_format($rango->precio_nocturno, 2, '.', ''),
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'local' => [
                        'id' => $negocio->id,
                        'nombre' => $negocio->name ?? 'Sin nombre',
                        'tiene_config_propia' => $tieneConfigPropia,
                    ],
                    'cliente' => [
                        'id' => $cliente->id,
                        'nombre' => $cliente->nombre . ' ' . $cliente->apellido,
                        'direccion' => $clienteDireccion->direccion
                    ],
                    'distancia_km' => round($distanciaKm, 2),
                    'precio_calculado' => number_format($precio, 2, '.', ''),
                    'modo_tarifa' => $modoTarifa,
                    'rango_aplicado' => $rangoAplicado,
                    'horario_nocturno' => $horarioNocturno,
                    'config_nombre' => $configNombre,
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
     * Obtener lista de locales para el select (simulador)
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
                ->whereNotNull('establecimientos.latitud')
                ->whereNotNull('establecimientos.longitud')
                ->orderBy('establecimientos.nombre_establecimiento')
                ->get()
                ->map(function($local) {
                    return [
                        'id' => $local->business_registration_id,
                        'nombre' => $local->nombre_establecimiento,
                        'coordenadas' => $local->latitud . ',' . $local->longitud
                    ];
                });

            return response()->json(['success' => true, 'data' => $locales]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener locales', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener lista de clientes para el select (simulador)
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

            return response()->json(['success' => true, 'data' => $clientes]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener clientes', 'error' => $e->getMessage()], 500);
        }
    }
}
