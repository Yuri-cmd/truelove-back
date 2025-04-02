<?php

namespace App\Http\Controllers;

use App\Models\RegistroVehiculo;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RegistroVehiculoController extends Controller
{
    public function guardar(Request $request)
    {
        try {
            Log::info('Datos recibidos:', $request->all());

            // Determinar si la solicitud es JSON
            $isJson = $request->isJson();

            // Validar campos de texto
            $validatedData = $request->validate([
                'reparto_registro_id' => 'required|exists:reparto_registros,id',
                'placa' => 'required|string|min:6',
                'licenciaConducir' => 'required|string|min:8',
                'seguro' => 'required|string|min:8',
                'tarjetaPropiedad' => 'required|string|min:8',
            ]);

            // Verificar si ya existe un registro para este repartidor
            $existingVehiculo = RegistroVehiculo::where('reparto_registro_id', $validatedData['reparto_registro_id'])->first();
            
            if ($existingVehiculo) {
                // Si ya existe, actualizar en lugar de crear
                return $this->actualizar($request, $existingVehiculo->id);
            }

            // Función para almacenar imagen
            $almacenarImagen = function($archivo, $carpeta) {
                if (!$archivo) return null;
                
                try {
                    $extension = $archivo->getClientOriginalExtension();
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $ruta = Storage::disk('custom_public')->putFileAs(
                        $carpeta,
                        $archivo,
                        $nombreArchivo
                    );
                    
                    return $ruta;
                } catch (\Exception $e) {
                    Log::error("Error al guardar archivo en {$carpeta}: " . $e->getMessage());
                    throw $e;
                }
            };

            // Procesar y guardar las imágenes
            $imagenes = [];
            $campos = [
                'placa_imagen' => 'placas',
                'licenciaConducir_imagen' => 'licencias',
                'seguro_imagen' => 'seguros',
                'tarjetaPropiedad_imagen' => 'tarjetas_propiedad'
            ];

            if (!$isJson) {
                foreach ($campos as $campo => $carpeta) {
                    if ($request->hasFile($campo)) {
                        $imagenes[$campo] = $almacenarImagen($request->file($campo), $carpeta);
                    }
                }
            } else {
                // Para solicitudes JSON, las imágenes se manejarían de manera diferente
                // Por ejemplo, podrían ser URLs o datos base64
                foreach ($campos as $campo => $carpeta) {
                    if ($request->has($campo)) {
                        // Aquí podrías procesar URLs o datos base64
                        $imagenes[$campo] = $request->input($campo);
                    }
                }
            }

            // Crear el registro
            $registroVehiculo = RegistroVehiculo::create([
                'reparto_registro_id' => $validatedData['reparto_registro_id'],
                'placa' => $validatedData['placa'],
                'licencia_conducir' => $validatedData['licenciaConducir'],
                'seguro' => $validatedData['seguro'],
                'tarjeta_propiedad' => $validatedData['tarjetaPropiedad'],
                'imagen_placa' => $imagenes['placa_imagen'] ?? null,
                'imagen_licencia' => $imagenes['licenciaConducir_imagen'] ?? null,
                'imagen_seguro' => $imagenes['seguro_imagen'] ?? null,
                'imagen_tarjeta_propiedad' => $imagenes['tarjetaPropiedad_imagen'] ?? null,
            ]);

            return response()->json([
                'mensaje' => 'Registro de vehículo creado exitosamente',
                'datos' => $registroVehiculo
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación: ' . json_encode($e->errors()));
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al guardar registro: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al guardar el registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function actualizar(Request $request, $id)
    {
        try {
            Log::info('Actualizando registro de vehículo ID: ' . $id, $request->all());

            // Validar campos de texto
            $validatedData = $request->validate([
                'placa' => 'required|string|min:6',
                'licenciaConducir' => 'required|string|min:8',
                'seguro' => 'required|string|min:8',
                'tarjetaPropiedad' => 'required|string|min:8',
            ]);

            // Buscar el registro
            $registroVehiculo = RegistroVehiculo::findOrFail($id);

            DB::beginTransaction();

            // Función para almacenar imagen
            $almacenarImagen = function($archivo, $carpeta) {
                if (!$archivo) return null;
                
                try {
                    $extension = $archivo->getClientOriginalExtension();
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $ruta = Storage::disk('custom_public')->putFileAs(
                        $carpeta,
                        $archivo,
                        $nombreArchivo
                    );
                    
                    return $ruta;
                } catch (\Exception $e) {
                    Log::error("Error al guardar archivo en {$carpeta}: " . $e->getMessage());
                    throw $e;
                }
            };

            // Procesar y guardar las imágenes
            $campos = [
                'placa_imagen' => ['campo_db' => 'imagen_placa', 'carpeta' => 'placas'],
                'licenciaConducir_imagen' => ['campo_db' => 'imagen_licencia', 'carpeta' => 'licencias'],
                'seguro_imagen' => ['campo_db' => 'imagen_seguro', 'carpeta' => 'seguros'],
                'tarjetaPropiedad_imagen' => ['campo_db' => 'imagen_tarjeta_propiedad', 'carpeta' => 'tarjetas_propiedad']
            ];

            $datosActualizar = [
                'placa' => $validatedData['placa'],
                'licencia_conducir' => $validatedData['licenciaConducir'],
                'seguro' => $validatedData['seguro'],
                'tarjeta_propiedad' => $validatedData['tarjetaPropiedad'],
            ];

            foreach ($campos as $campo => $info) {
                if ($request->hasFile($campo)) {
                    // Eliminar imagen anterior si existe
                    if ($registroVehiculo->{$info['campo_db']}) {
                        Storage::disk('custom_public')->delete($registroVehiculo->{$info['campo_db']});
                    }
                    
                    // Guardar nueva imagen
                    $datosActualizar[$info['campo_db']] = $almacenarImagen($request->file($campo), $info['carpeta']);
                }
            }

            // Actualizar el registro
            $registroVehiculo->update($datosActualizar);

            DB::commit();

            return response()->json([
                'mensaje' => 'Registro de vehículo actualizado exitosamente',
                'datos' => $registroVehiculo
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación: ' . json_encode($e->errors()));
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar registro: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al actualizar el registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function mostrar($repartoRegistroId)
    {
        try {
            Log::info('Obteniendo registro de vehículo para el ID: ' . $repartoRegistroId);
            
            // Verificar que el registro existe
            $repartoRegistro = RepartoRegistro::findOrFail($repartoRegistroId);
            
            // Obtener el registro de vehículo asociado
            $registroVehiculo = RegistroVehiculo::where('reparto_registro_id', $repartoRegistroId)->first();
            
            if (!$registroVehiculo) {
                return response()->json([
                    'mensaje' => 'No se encontró registro de vehículo para este repartidor',
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
            
            // Preparar URLs de imágenes
            $imagenes = [
                'imagen_placa' => $registroVehiculo->imagen_placa ? url(Storage::disk('custom_public')->url($registroVehiculo->imagen_placa)) : null,
                'imagen_licencia' => $registroVehiculo->imagen_licencia ? url(Storage::disk('custom_public')->url($registroVehiculo->imagen_licencia)) : null,
                'imagen_seguro' => $registroVehiculo->imagen_seguro ? url(Storage::disk('custom_public')->url($registroVehiculo->imagen_seguro)) : null,
                'imagen_tarjeta_propiedad' => $registroVehiculo->imagen_tarjeta_propiedad ? url(Storage::disk('custom_public')->url($registroVehiculo->imagen_tarjeta_propiedad)) : null,
            ];
            
            return response()->json([
                'registro_vehiculo' => [
                    'id' => $registroVehiculo->id,
                    'placa' => $registroVehiculo->placa,
                    'licencia_conducir' => $registroVehiculo->licencia_conducir,
                    'seguro' => $registroVehiculo->seguro,
                    'tarjeta_propiedad' => $registroVehiculo->tarjeta_propiedad,
                    'imagenes' => $imagenes,
                ],
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
            Log::error('Error al obtener registro de vehículo: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener el registro de vehículo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

