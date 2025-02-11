<?php

namespace App\Http\Controllers;

use App\Models\CategoriaAdicional;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoriaAdicionalController extends Controller
{
    public function index()
    {
        try {
            $categorias = CategoriaAdicional::all();
            if ($categorias->isEmpty()) {
                return response()->json(['message' => 'No se encontraron categorías adicionales'], 404);
            }
            return response()->json($categorias);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener las categorías adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'nombre' => 'required|string|max:255',
            ]);

            $categoria = CategoriaAdicional::create($request->all());
            return response()->json($categoria, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $categoria = CategoriaAdicional::findOrFail($id);
            return response()->json($categoria);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Categoría adicional no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $categoria = CategoriaAdicional::findOrFail($id);
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'nombre' => 'required|string|max:255',
            ]);

            $categoria->update($request->all());
            return response()->json($categoria);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Categoría adicional no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $categoria = CategoriaAdicional::findOrFail($id);
            $categoria->delete();
            return response()->json(['message' => 'Categoría adicional eliminada con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Categoría adicional no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }
}