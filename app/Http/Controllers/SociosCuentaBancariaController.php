<?php

namespace App\Http\Controllers;

use App\Models\SociosCuentaBancaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
            $imagenes = [];
            foreach ($request->file('imagenes_cuenta') as $imagen) {
                $rutaImagen = $imagen->store('cuentas_bancarias_socios', 'public');
                $imagenes[] = $rutaImagen;
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
            foreach ($imagenes as $rutaImagen) {
                Storage::disk('public')->delete($rutaImagen);
            }

            return response()->json([
                'mensaje' => 'Error al guardar la cuenta bancaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}