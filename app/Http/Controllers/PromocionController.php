<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use Illuminate\Http\Request;

class PromocionController extends Controller
{
    public function index()
    {
        return response()->json(Promocion::where('estado', 1)->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'subtitulo' => 'required|string|max:255',
            'imagen' => 'nullable|string',
            'estado' => 'boolean'
        ]);

        $promocion = Promocion::create($data);
        return response()->json($promocion, 201);
    }

    public function show($id)
    {
        return response()->json(Promocion::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $promocion = Promocion::findOrFail($id);
        $data = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'subtitulo' => 'sometimes|required|string|max:255',
            'imagen' => 'nullable|string',
            'estado' => 'boolean'
        ]);

        $promocion->update($data);
        return response()->json($promocion);
    }

    public function destroy($id)
    {
        $promocion = Promocion::findOrFail($id);
        $promocion->delete();
        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
