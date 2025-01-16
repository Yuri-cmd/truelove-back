<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categorias;

class CategoriaController extends Controller
{
    public function index($empresa_id)
    {
        $categorias = Categorias::where('empresa_id', $empresa_id)->get();
        return response()->json($categorias);
    }

    // Crear categoría
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'id_empresa' => 'required|string|max:255',
        ]);

        $category = Categorias::create([
            'name' => $request->name,
            'empresa_id' => $request->id_empresa,
        ]);

        return response()->json($category, 201);
    }

    // Actualizar categoría
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Categorias::findOrFail($id);
        $category->update([
            'name' => $request->name,
            'empresa_id' => $request->id_empresa,
        ]);

        return response()->json($category, 200);
    }

    // Eliminar categoría
    public function destroy($id, $id_empresa)
    {
        $category = Categorias::where('id', $id)->where('empresa_id', $id_empresa)->firstOrFail();
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada con éxito'], 200);
    }
}
