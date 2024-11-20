<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SucursalController extends Controller
{
    public function store(Request $request, Negocio $negocio)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'telefono' => 'nullable|regex:/^\+51\d{9}$/',
            'es_sucursal_principal' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $sucursal = $negocio->sucursales()->create($request->validated());
            DB::commit();

            return response()->json([
                'mensaje' => 'Sucursal registrada exitosamente',
                'sucursal' => $sucursal
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al registrar la sucursal'], 500);
        }
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'direccion' => 'sometimes|string|max:255',
            'latitud' => 'sometimes|numeric|between:-90,90',
            'longitud' => 'sometimes|numeric|between:-180,180',
            'telefono' => 'nullable|regex:/^\+51\d{9}$/',
            'es_sucursal_principal' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $sucursal->update($request->validated());
            DB::commit();

            return response()->json([
                'mensaje' => 'Sucursal actualizada exitosamente',
                'sucursal' => $sucursal
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al actualizar la sucursal'], 500);
        }
    }
}