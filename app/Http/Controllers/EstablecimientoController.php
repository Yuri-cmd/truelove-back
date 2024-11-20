<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstablecimientoController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'businessName' => 'required|string|min:2',
            'street' => 'required|string|min:2',
            'number' => 'required|string|min:1',
            'postalCode' => 'required|string|min:5',
            'province' => 'required|string|min:2',
            'city' => 'required|string|min:2',
            'reference' => 'nullable|string',
            'coordinates' => 'required|array',
            'coordinates.0' => 'required|numeric',
            'coordinates.1' => 'required|numeric',
            'fullAddress' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $establecimiento = Establecimiento::create([
                'nombre_establecimiento' => $request->businessName,
                'calle' => $request->street,
                'numero' => $request->number,
                'codigo_postal' => $request->postalCode,
                'provincia' => $request->province,
                'ciudad' => $request->city,
                'referencia' => $request->reference,
                'latitud' => $request->coordinates[1],
                'longitud' => $request->coordinates[0],
                'direccion_completa' => $request->fullAddress
            ]);

            return response()->json([
                'message' => 'Establecimiento registrado exitosamente',
                'establecimiento' => $establecimiento
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el establecimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}