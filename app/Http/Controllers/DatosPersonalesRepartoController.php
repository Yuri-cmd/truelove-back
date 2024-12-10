<?php

namespace App\Http\Controllers;

use App\Models\Ciudad;
use App\Models\DatosPersonalesReparto;
use App\Models\Distrito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DatosPersonalesRepartoController extends Controller
{
    public function obtenerCiudades()
    {
        $ciudades = Ciudad::all();
        return response()->json($ciudades);
    }

    public function obtenerDistritos($ciudadId)
    {
        $distritos = Distrito::where('ciudad_id', $ciudadId)->get();
        return response()->json($distritos);
    }

    public function guardar(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:masculino,femenino,otro',
            'selfie' => 'required|image|max:2048', // 2MB Max
            'ciudad_id' => 'required|exists:ciudades,id',
            'distrito_id' => 'required|exists:distritos,id',
        ]);

        if ($validador->fails()) {
            return response()->json(['errores' => $validador->errors()], 422);
        }

        $urlSelfie = $request->file('selfie')->store('selfies', 'public');

        $datosPersonales = DatosPersonalesReparto::create([
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'genero' => $request->genero,
            'url_selfie' => $urlSelfie,
            'ciudad_id' => $request->ciudad_id,
            'distrito_id' => $request->distrito_id,
        ]);

        return response()->json($datosPersonales, 201);
    }
}

