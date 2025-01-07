<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\DatosPersonalesReparto;
use App\Models\Distrito;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatosPersonalesRepartoController extends Controller
{
    public function obtenerCiudades()
    {
        try {
            $ciudades = Ciudad::all();
            return response()->json($ciudades);
        } catch (\Exception $e) {
            Log::error('Error al obtener ciudades: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener ciudades'], 500);
        }
    }

    public function obtenerDistritos($ciudadId)
    {
        try {
            $distritos = Distrito::where('ciudad_id', $ciudadId)->get();
            return response()->json($distritos);
        } catch (\Exception $e) {
            Log::error('Error al obtener distritos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener distritos'], 500);
        }
    }

    public function guardar(Request $request)
    {
        Log::info('Datos recibidos:', $request->all());

        $validator = Validator::make($request->all(), [
            'reparto_registro_id' => 'required|exists:reparto_registros,id',
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:masculino,femenino,otro',
            'selfie' => 'required|image|max:2048', // 2MB Max
            'ciudad_id' => 'required|exists:ciudades,id',
            'distrito_id' => 'required|exists:distritos,id',
        ]);

        if ($validator->fails()) {
            Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Verificar que el registro existe
            $repartoRegistro = RepartoRegistro::findOrFail($request->reparto_registro_id);

            // Función para almacenar imagen
            $almacenarSelfie = function($archivo) {
                if (!$archivo) return null;
                
                try {
                    $extension = $archivo->getClientOriginalExtension();
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $ruta = Storage::disk('custom_public')->putFileAs(
                        'selfies',
                        $archivo,
                        $nombreArchivo
                    );
                    
                    return $ruta;
                } catch (\Exception $e) {
                    Log::error("Error al guardar selfie: " . $e->getMessage());
                    throw $e;
                }
            };

            // Almacenar la selfie usando el mismo método que las imágenes de vehículos
            $urlSelfie = $almacenarSelfie($request->file('selfie'));

            $datosPersonales = DatosPersonalesReparto::create([
                'reparto_registro_id' => $request->reparto_registro_id,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'url_selfie' => $urlSelfie,
                'ciudad_id' => $request->ciudad_id,
                'distrito_id' => $request->distrito_id,
            ]);

            DB::commit();

            Log::info('Datos personales guardados:', $datosPersonales->toArray());

            return response()->json([
                'mensaje' => 'Datos personales guardados correctamente',
                'datos' => $datosPersonales
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar datos personales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al guardar los datos personales',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}