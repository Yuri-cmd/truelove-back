<?php

namespace App\Http\Controllers;


use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RepartoRegistroController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'departamento' => 'required|string',
            'vehiculo' => 'required|string',
            'tipo_documento' => 'required|string',
            'nro_documento' => 'required|string',
            'nombres' => 'required|string',
            'apellidos' => 'nullable|string',
            'celular' => 'required|string',
            'email' => 'required|email',
            'mayor_edad' => 'required|boolean',
            'acepta_politica' => 'required|boolean',
            'documento_imagen' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $registro = RepartoRegistro::create($validator->validated());

        return response()->json(['message' => 'Registro creado exitosamente', 'data' => $registro], 201);
    }
}

