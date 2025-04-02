<?php

namespace App\Http\Controllers;

use App\Models\DatosPersonalesReparto;
use App\Models\RepartoRegistro;
use App\Models\UbigeoInei;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatosPersonalesRepartoController extends Controller
{
    public function obtenerDepartamentos()
    {
        try {
            $departamentos = UbigeoInei::getDepartamentos();
            return response()->json($departamentos);
        } catch (\Exception $e) {
            Log::error('Error al obtener departamentos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener departamentos'], 500);
        }
    }

    public function obtenerProvincias($departamentoId)
    {
        try {
            $provincias = UbigeoInei::getProvincias($departamentoId);
            return response()->json($provincias);
        } catch (\Exception $e) {
            Log::error('Error al obtener provincias: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener provincias'], 500);
        }
    }

    public function obtenerDistritos($departamentoId, $provinciaId)
    {
        try {
            $distritos = UbigeoInei::getDistritos($departamentoId, $provinciaId);
            return response()->json($distritos);
        } catch (\Exception $e) {
            Log::error('Error al obtener distritos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener distritos'], 500);
        }
    }

    public function show($repartoRegistroId)
    {
        try {
            Log::info('Obteniendo datos personales para el registro ID: ' . $repartoRegistroId);
            
            // Verificar que el registro existe
            $repartoRegistro = RepartoRegistro::findOrFail($repartoRegistroId);
            
            // Obtener los datos personales asociados
            $datosPersonales = DatosPersonalesReparto::where('reparto_registro_id', $repartoRegistroId)->first();
            
            if (!$datosPersonales) {
                return response()->json([
                    'message' => 'No se encontraron datos personales para este registro',
                    'datos_basicos' => [
                        'nombres' => $repartoRegistro->nombres,
                        'apellidos' => $repartoRegistro->apellidos,
                        'email' => $repartoRegistro->email,
                        'celular' => $repartoRegistro->celular,
                        'tipo_documento' => $repartoRegistro->tipo_documento,
                        'nro_documento' => $repartoRegistro->nro_documento,
                    ]
                ], 200);
            }
            
            // Obtener información de ubicación
            $ubicacion = [];
            if ($datosPersonales->ubigeo_id) {
                $ubigeo = UbigeoInei::find($datosPersonales->ubigeo_id);
                if ($ubigeo) {
                    $ubicacion = [
                        'departamento' => $ubigeo->departamento,
                        'provincia' => $ubigeo->provincia,
                        'distrito' => $ubigeo->distrito,
                        'ubigeo_id' => $datosPersonales->ubigeo_id
                    ];
                }
            }
            
            // Preparar la URL de la selfie
            $selfieUrl = null;
            if ($datosPersonales->url_selfie) {
                $selfieUrl = url(Storage::disk('custom_public')->url($datosPersonales->url_selfie));
            }
            
            return response()->json([
                'datos_personales' => [
                    'id' => $datosPersonales->id,
                    'fecha_nacimiento' => $datosPersonales->fecha_nacimiento->format('Y-m-d'),
                    'genero' => $datosPersonales->genero,
                    'url_selfie' => $selfieUrl,
                ],
                'ubicacion' => $ubicacion,
                'datos_basicos' => [
                    'nombres' => $repartoRegistro->nombres,
                    'apellidos' => $repartoRegistro->apellidos,
                    'email' => $repartoRegistro->email,
                    'celular' => $repartoRegistro->celular,
                    'tipo_documento' => $repartoRegistro->tipo_documento,
                    'nro_documento' => $repartoRegistro->nro_documento,
                ]
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener datos personales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los datos personales',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info('Actualizando datos personales ID: ' . $id, $request->all());
        
        $validator = Validator::make($request->all(), [
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:masculino,femenino,otro',
            'selfie' => 'nullable|image|max:2048', // 2MB Max
            'ubigeo_id' => 'required|exists:ubigeo_inei,id_ubigeo',
        ]);
        
        if ($validator->fails()) {
            Log::error('Validación fallida en actualización:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        
        try {
            // Buscar el registro de datos personales
            $datosPersonales = DatosPersonalesReparto::findOrFail($id);
            
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
            
            // Datos a actualizar
            $datosActualizar = [
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'ubigeo_id' => $request->ubigeo_id,
            ];
            
            // Si se envió una nueva selfie, actualizarla
            if ($request->hasFile('selfie')) {
                // Eliminar la selfie anterior si existe
                if ($datosPersonales->url_selfie) {
                    Storage::disk('custom_public')->delete($datosPersonales->url_selfie);
                }
                
                // Almacenar la nueva selfie
                $urlSelfie = $almacenarSelfie($request->file('selfie'));
                $datosActualizar['url_selfie'] = $urlSelfie;
            }
            
            // Actualizar los datos
            $datosPersonales->update($datosActualizar);
            
            DB::commit();
            
            Log::info('Datos personales actualizados:', $datosPersonales->toArray());
            
            return response()->json([
                'mensaje' => 'Datos personales actualizados correctamente',
                'datos' => $datosPersonales
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar datos personales: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar los datos personales',
                'details' => $e->getMessage()
            ], 500);
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
            'ubigeo_id' => 'required|exists:ubigeo_inei,id_ubigeo',
        ]);

        if ($validator->fails()) {
            Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Verificar que el registro existe
            $repartoRegistro = RepartoRegistro::findOrFail($request->reparto_registro_id);

            // Verificar si ya existe un registro de datos personales para este repartidor
            $existingData = DatosPersonalesReparto::where('reparto_registro_id', $request->reparto_registro_id)->first();
            
            if ($existingData) {
                // Si ya existe, actualizar en lugar de crear
                return $this->update($request, $existingData->id);
            }

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
                'ubigeo_id' => $request->ubigeo_id,
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

