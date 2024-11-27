<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\TipoCuentaBancaria;
use App\Models\CuentaBancaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DatosBancariosRepartoController extends Controller
{
    public function obtenerBancos()
    {
        $bancos = Banco::all();
        return response()->json($bancos);
    }

    public function obtenerTiposCuenta()
    {
        $tiposCuenta = TipoCuentaBancaria::all();
        return response()->json($tiposCuenta);
    }

    public function guardarCuentaBancaria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titular' => 'required|string|max:255',
            'dni' => 'required|string|max:20',
            'banco_id' => 'required|exists:bancos,id',
            'tipo_cuenta_id' => 'required|exists:tipos_cuenta_bancaria,id',
            'numero_cuenta' => 'required|string|max:50',
            'imagen_cuenta' => 'required|file|mimes:jpeg,png,pdf|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }

        try {
            // Guardar la imagen
            $rutaImagen = $request->file('imagen_cuenta')->store('cuentas_bancarias', 'public');

            // Crear y guardar la cuenta bancaria
            $cuentaBancaria = CuentaBancaria::create([
                'titular' => $request->titular,
                'dni' => $request->dni,
                'banco_id' => $request->banco_id,
                'tipo_cuenta_id' => $request->tipo_cuenta_id,
                'numero_cuenta' => $request->numero_cuenta,
                'url_imagen_cuenta' => $rutaImagen
            ]);

            return response()->json([
                'mensaje' => 'Cuenta bancaria guardada con éxito',
                'cuenta_bancaria' => $cuentaBancaria
            ], 201);
        } catch (\Exception $e) {
            // Si algo falla, eliminamos la imagen si se subió
            if (isset($rutaImagen)) {
                Storage::disk('public')->delete($rutaImagen);
            }

            return response()->json([
                'mensaje' => 'Error al guardar la cuenta bancaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}