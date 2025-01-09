<?php

namespace App\Http\Controllers;

use App\Models\SociosCuentaBancaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SociosCuentaBancariaController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_registration_id' => 'required|exists:business_registrations,id',
            'titular_cuenta' => 'required|string|max:255',
            'dni' => 'required|string|max:20',
            'banco_id' => 'required|exists:bancos,id',
            'tipo_cuenta_id' => 'required|exists:tipos_cuenta_bancaria,id',
            'numero_cuenta' => 'required|string|max:50',
            'imagenes_cuenta' => 'required|array',
            'imagenes_cuenta.*' => 'file|mimes:jpeg,png,pdf|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }

        try {
            // Función para almacenar imagen
            $almacenarImagen = function($archivo) {
                if (!$archivo) return null;
                
                try {
                    $extension = $archivo->getClientOriginalExtension();
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $ruta = Storage::disk('custom_public')->putFileAs(
                        'cuentas_bancarias_socios',
                        $archivo,
                        $nombreArchivo
                    );
                    
                    return $ruta;
                } catch (\Exception $e) {
                    Log::error("Error al guardar archivo en cuentas_bancarias_socios: " . $e->getMessage());
                    throw $e;
                }
            };

            $imagenes = [];
            foreach ($request->file('imagenes_cuenta') as $imagen) {
                $rutaImagen = $almacenarImagen($imagen);
                if ($rutaImagen) {
                    $imagenes[] = $rutaImagen;
                }
            }

            if (empty($imagenes)) {
                throw new \Exception('No se pudieron guardar las imágenes');
            }

            $cuentaBancaria = SociosCuentaBancaria::create([
                'business_registration_id' => $request->business_registration_id,
                'titular_cuenta' => $request->titular_cuenta,
                'dni' => $request->dni,
                'banco_id' => $request->banco_id,
                'tipo_cuenta_id' => $request->tipo_cuenta_id,
                'numero_cuenta' => $request->numero_cuenta,
                'imagenes_cuenta' => json_encode($imagenes)
            ]);

            return response()->json([
                'mensaje' => 'Cuenta bancaria guardada con éxito',
                'cuenta_bancaria' => $cuentaBancaria
            ], 201);
        } catch (\Exception $e) {
            // Si algo falla, eliminamos las imágenes si se subieron
            if (!empty($imagenes)) {
                foreach ($imagenes as $rutaImagen) {
                    Storage::disk('custom_public')->delete($rutaImagen);
                }
            }

            Log::error('Error al guardar cuenta bancaria: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al guardar la cuenta bancaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}