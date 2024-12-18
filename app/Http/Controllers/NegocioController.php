<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Negocio;
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
            'tipo_negocio_id' => 'required|exists:tipos_negocios,id',
            'categoria_id' => 'required|exists:categorias,id',
            'total_sucursales' => 'required|integer|min:1',
            'es_local_calle' => 'required|boolean',
            'metodo_contacto' => 'required|in:WhatsApp,Llamada,SMS',
            'telefono' => 'required|regex:/^\+51\d{9}$/',
            'business_registration_id' => 'required|exists:business_registrations,id', // Agregar esta validación
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
                'tipo_negocio_id' => $request->tipo_negocio_id,
                'categoria_id' => $request->categoria_id,
                'user_id' => 1, // Ajusta esto según tu lógica de autenticación
                'total_sucursales' => $request->total_sucursales,
                'es_local_calle' => $request->es_local_calle,
                'metodo_contacto' => $request->metodo_contacto,
                'telefono' => $request->telefono,
                'business_registration_id' => $request->business_registration_id,
                'activo' => true,
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
}