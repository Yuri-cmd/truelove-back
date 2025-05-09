<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedioPagoController extends Controller
{
    public function index()
    {
        $mediosPago = MedioPago::where('estado', 1)
            ->get(['id', 'nombre', 'estado']);
        return response()->json($mediosPago, 200);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:medio_de_pagos,nombre',
            'estado' => 'required|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $medioPago = MedioPago::create([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
        ]);
        
        return response()->json($medioPago, 201);
    }
    
    public function show($id)
    {
        $medioPago = MedioPago::findOrFail($id);
        return response()->json($medioPago, 200);
    }
    
    public function update(Request $request, $id)
    {
        $medioPago = MedioPago::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:medio_de_pagos,nombre,' . $id,
            'estado' => 'required|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $medioPago->update([
            'nombre' => $request->nombre,
            'estado' => $request->estado,
        ]);
        
        return response()->json($medioPago, 200);
    }
    
    public function destroy($id)
    {
        $medioPago = MedioPago::findOrFail($id);
        $medioPago->delete();
        
        return response()->json(null, 204);
    }
}
