<?php

namespace App\Http\Controllers;

use App\Models\RegistroVehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
                'placa' => 'required|string|min:6',
                'licenciaConducir' => 'required|string|min:8',
                'seguro' => 'required|string|min:8',
                'tarjetaPropiedad' => 'required|string|min:8',
            ]);

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
}

