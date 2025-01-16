<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\CuentaBancariaReparto;
use App\Models\TipoCuentaBancaria;
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
            'reparto_registro_id' => 'required|exists:reparto_registros,id',
            'titular' => 'required|string|max:255',
            'dni' => 'required|digits:8',
            'banco_id' => 'required|exists:bancos,id',
            'tipo_cuenta_id' => 'required|exists:tipos_cuenta_bancaria,id',
            'numero_cuenta' => 'required|string|max:50',
            'imagen_cuenta' => 'required|file|mimes:jpeg,png,pdf|max:4096',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }
    
        try {
            // Generar un nombre único para la imagen
            $nombreImagen = time() . '_' . $request->file('imagen_cuenta')->getClientOriginalName();
            
            // Guardar la imagen directamente en la carpeta public
            $rutaImagen = $request->file('imagen_cuenta')->move(public_path('storage/cuentas_bancarias'), $nombreImagen);
    
            // Generar la URL pública de la imagen
            $urlImagen = asset('storage/cuentas_bancarias/' . $nombreImagen);
    
            // Crear y guardar la cuenta bancaria
            $cuentaBancaria = CuentaBancariaReparto::create([
                'reparto_registro_id' => $request->reparto_registro_id,
                'titular' => $request->titular,
                'dni' => $request->dni,
                'banco_id' => $request->banco_id,
                'tipo_cuenta_id' => $request->tipo_cuenta_id,
                'numero_cuenta' => $request->numero_cuenta,
                'url_imagen_cuenta' => $urlImagen // Guardamos la URL pública
            ]);
    
            return response()->json([
                'mensaje' => 'Cuenta bancaria guardada con éxito',
                'cuenta_bancaria' => $cuentaBancaria
            ], 201);
        } catch (\Exception $e) {
            // Si algo falla, eliminamos la imagen si se subió
            if (isset($rutaImagen) && file_exists($rutaImagen)) {
                unlink($rutaImagen);
            }
    
            return response()->json([
                'mensaje' => 'Error al guardar la cuenta bancaria',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}