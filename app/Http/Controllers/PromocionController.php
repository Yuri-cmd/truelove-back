<?php

namespace App\Http\Controllers;

use App\Models\Promocion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class PromocionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->showAll === 'true') {
            return response()->json(Promocion::all());
        }
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
        if ($request->hasFile('imagen')) {
            $imagePath = $request->file('imagen')->store('promociones-img', 'custom_public');
            $data['imagen'] = $imagePath;
        }

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
        if ($request->hasFile('imagen')) {
            if ($promocion->imagen) {
                Storage::disk('custom_public')->delete($promocion->imagen);
            }

            $imagePath = $request->file('imagen')->store('promociones-img', 'custom_public');
            $data['imagen'] = $imagePath;
        }

        $promocion->update($data);
        return response()->json($promocion);
    }

    public function destroy($id)
    {
        $promocion = Promocion::findOrFail($id);
        // Eliminar la imagen si existe
        if ($promocion->imagen) {
            Storage::disk('custom_public')->delete($promocion->imagen);
        }
        $promocion->delete();
        return response()->json(['message' => 'Eliminado correctamente']);
    }
}
