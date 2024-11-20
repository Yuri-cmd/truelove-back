<?php

namespace App\Http\Controllers;

use App\Models\DatosClaveNegocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DatosClaveNegocioController extends Controller
{
    public function guardar(Request $request)
    {
        Log::info('Datos recibidos:', $request->all());

        $validador = Validator::make($request->all(), [
            'ruc' => 'required|string|size:11',
            'razon_social' => 'required|string|max:255',
        ]);

        if ($validador->fails()) {
            Log::error('Validación fallida:', $validador->errors()->toArray());
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validador->errors()
            ], 422);
        }

        try {
            $datosClaveNegocio = DatosClaveNegocio::create([
                'ruc' => $request->ruc,
                'razon_social' => $request->razon_social,
            ]);

            Log::info('Datos guardados exitosamente:', $datosClaveNegocio->toArray());

            return response()->json([
                'mensaje' => 'Datos guardados exitosamente',
                'datos' => $datosClaveNegocio
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al guardar datos:', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);

            return response()->json([
                'mensaje' => 'Error al guardar los datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}