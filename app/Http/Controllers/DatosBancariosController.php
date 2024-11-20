<?php

namespace App\Http\Controllers;

use App\Models\DatosBancarios;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DatosBancariosController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titular_cuenta' => 'required|string|max:255',
            'numero_cuenta' => 'required|string|max:255',
            'nombre_banco' => 'required|string|max:255',
            'tipo_cuenta' => 'required|string|max:255',
            'documento_titular' => 'required|string|max:255',
            'codigo_cci' => 'required|string|max:255',
            'usar_direccion_negocio' => 'required|boolean',
            'establecimiento_id' => 'required|exists:establecimientos,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validator->errors()
            ], 422);
        }

        try {
            $datosBancarios = DatosBancarios::create($request->all());

            return response()->json([
                'mensaje' => 'Datos bancarios guardados exitosamente',
                'datos' => $datosBancarios
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al guardar datos bancarios: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al guardar los datos bancarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEstablecimientoDireccion($id)
    {
        try {
            $establecimiento = Establecimiento::findOrFail($id);
            
            return response()->json([
                'direccion' => [
                    'calle' => $establecimiento->calle,
                    'numero' => $establecimiento->numero,
                    'codigo_postal' => $establecimiento->codigo_postal,
                    'provincia' => $establecimiento->provincia,
                    'ciudad' => $establecimiento->ciudad,
                    'referencia' => $establecimiento->referencia,
                    'direccion_completa' => $establecimiento->direccion_completa
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener dirección del establecimiento: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener la dirección del establecimiento',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}