<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\HorarioNegocio;
use App\Models\Negocio;
use App\Models\PerfilNegocio;
use App\Models\TipoNegocio;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NegocioController extends Controller
{
    public function getTiposNegocio()
    {
        try {
            $tipos = TipoNegocio::where('activo', true)->get();
            return response()->json($tipos);
        } catch (\Exception $e) {
            Log::error('Error al obtener tipos de negocio: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCategorias($tipoNegocioId)
    {
        try {
            $categorias = Categoria::where('tipo_negocio_id', $tipoNegocioId)
                ->where('activo', true)
                ->get(['id', 'nombre']);

            Log::info('Categorias retrieved:', [
                'tipo_negocio_id' => $tipoNegocioId,
                'count' => $categorias->count()
            ]);

            return response()->json($categorias);
        } catch (\Exception $e) {
            Log::error('Error al obtener las categorias: ' . $e->getMessage());
            return response()->json(['error' => 'Error categorias'], 500);
        }
    }

    public function store(Request $request)
    {
        Log::info('Datos recibidos:', $request->all());

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255',
            'total_sucursales' => 'required|integer|min:1',
            'es_local_calle' => 'required|boolean',
            'metodo_contacto' => 'required|in:WhatsApp,Llamada,SMS',
            'telefono' => 'required|regex:/^\+51\d{9}$/',
            'business_registration_id' => 'required|exists:business_registrations,id', // Agregar esta validación
            'tipo_pago_digital' => 'required|integer|in:0,1,2',
            'numero_pago_digital' => 'nullable|string|regex:/^\d{9}$/',
            'nombre_titular_pago_digital' => 'nullable|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Verificar que el registro existe y está verificado
            $businessRegistration = BusinessRegistration::where('id', $request->business_registration_id)
                ->whereNotNull('email_verified_at')
                ->first();

            if (!$businessRegistration) {
                throw new \Exception('Registro de negocio no encontrado o email no verificado');
            }

            $negocio = Negocio::create([
                'nombre' => $request->nombre,
                'user_id' => 1, // Ajusta esto según tu lógica de autenticación
                'total_sucursales' => $request->total_sucursales,
                'es_local_calle' => $request->es_local_calle,
                'metodo_contacto' => $request->metodo_contacto,
                'telefono' => $request->telefono,
                'business_registration_id' => $request->business_registration_id,
                'activo' => true,
                'tipo_pago_digital' => $request->tipo_pago_digital,
                'numero_pago_digital' => $request->numero_pago_digital,
                'nombre_titular_pago_digital' => $request->nombre_titular_pago_digital,
            ]);

            DB::commit();

            return response()->json([
                'mensaje' => 'Negocio registrado exitosamente',
                'negocio' => $negocio
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear negocio: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al registrar el negocio',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    //actualizar negocio
    public function update(Request $request, Negocio $negocio)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|min:2|max:255',
            'tipo_negocio_id' => 'sometimes|exists:tipos_negocios,id',
            'categoria_id' => 'sometimes|exists:categorias,id',
            'total_sucursales' => 'sometimes|integer|min:1',
            'es_local_calle' => 'sometimes|boolean',
            'metodo_contacto' => 'sometimes|in:WhatsApp,Llamada,SMS',
            'telefono' => 'sometimes|regex:/^\+51\d{9}$/',
            'tipo_pago_digital' => 'sometimes|integer|in:0,1,2',
            'numero_pago_digital' => 'nullable|string|regex:/^\d{9}$/',
            'nombre_titular_pago_digital' => 'nullable|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $negocio->update($request->all());
            DB::commit();

            return response()->json([
                'mensaje' => 'Negocio actualizado exitosamente',
                'negocio' => $negocio
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar negocio: ' . $e->getMessage());
            return response()->json(['error' => 'Error al actualizar el negocio'], 500);
        }
    }
    public function show($businessRegistrationId)
    {
        try {
            $negocio = Negocio::where('business_registration_id', $businessRegistrationId)
                ->first();

            if (!$negocio) {
                return response()->json(['message' => 'Negocio no encontrado'], 404);
            }

            // Convertir el número a string para el frontend
            $negocio->tipo_pago_digital = (string) $negocio->tipo_pago_digital;

            return response()->json($negocio);
        } catch (\Exception $e) {
            Log::error('Error al obtener negocio: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener los datos del negocio'], 500);
        }
    }


    public function checkApprovalStatus($businessRegistrationId)
    {
        try {
            $businessRegistration = BusinessRegistration::findOrFail($businessRegistrationId);
            $negocio = $businessRegistration->negocio;

            return response()->json([
                'aprobado' => $businessRegistration->aprobado,
                'nombre_negocio' => $negocio ? $negocio->nombre : null
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar el estado de aprobación: ' . $e->getMessage());
            return response()->json(['error' => 'Error al verificar el estado de aprobación'], 500);
        }
    }

    public function localEstaAbierto($idLocal)
    {
        $abierto = false;

        $businessRegistration = BusinessRegistration::findOrFail($idLocal);
        $negocio = PerfilNegocio::where('business_registration_id', $idLocal)->first();
        $horarioNegocio = HorarioNegocio::where('perfil_negocio_id', $negocio->id)->first();
        // 1. Default: usar el estado activo del registro de negocio (si no hay horario detallado)
        $abierto = $businessRegistration->activo == 1;

        // 2. Si existe un horario definido, verificamos días y horas
        if ($horarioNegocio && $horarioNegocio->activo) {
            $now = \Carbon\Carbon::now();
            $dias = [
                'Monday' => 'lunes',
                'Tuesday' => 'martes',
                'Wednesday' => 'miercoles',
                'Thursday' => 'jueves',
                'Friday' => 'viernes',
                'Saturday' => 'sabado',
                'Sunday' => 'domingo',
            ];

            $diaActualEnglish = $now->format('l'); // Monday, Tuesday...
            $campoDia = $dias[$diaActualEnglish];
            // Verificamos si abre hoy
            if ($horarioNegocio->$campoDia) {
                // Verificamos el rango de hora
                $horaActual = $now->format('H:i:s');

                if ($horarioNegocio->hora_cierre < $horarioNegocio->hora_apertura) {
                    // El horario cruza la medianoche (ej: 09:00 a 01:00)
                    if ($horaActual >= $horarioNegocio->hora_apertura || $horaActual <= $horarioNegocio->hora_cierre) {
                        $abierto = true;
                    } else {
                        $abierto = false;
                    }
                } else {
                    // Horario normal (ej: 09:00 a 22:00)
                    if ($horaActual >= $horarioNegocio->hora_apertura && $horaActual <= $horarioNegocio->hora_cierre) {
                        $abierto = true;
                    } else {
                        $abierto = false;
                    }
                }
            } else {
                // No abre hoy
                $abierto = false;
            }
        }

        return response()->json([
            'abierto' => $abierto
        ]);
    }
}