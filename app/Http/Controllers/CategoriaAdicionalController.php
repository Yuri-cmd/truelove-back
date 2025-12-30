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
            // Retornar 200 con array vacío si no hay categorías, en lugar de 404
            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => $categorias->isEmpty()
                    ? 'No se encontraron categorías adicionales'
                    : 'Categorías obtenidas correctamente'
            ], 200);
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
    public function obtenerCategoriasAdicionales($empresa_id)
    {
        try {
            $categorias = CategoriaAdicional::where('empresa_id', $empresa_id)->get();
            // Retornar 200 con array vacío si no hay categorías, en lugar de 404
            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => $categorias->isEmpty()
                    ? 'No se encontraron categorías adicionales para esta empresa'
                    : 'Categorías obtenidas correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener las categorías adicionales', 'error' => $e->getMessage()], 500);
        }
    }

    public function crearCategoriaAdicional(Request $request)
    {
        try {
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'nombre' => 'required|string|max:255',
            ]);

            $categoria = CategoriaAdicional::create($request->all());
            return response()->json(['success' => true, 'data' => $categoria, 'message' => 'Categoría adicional creada con éxito'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function obtenerCategoriaAdicional($id)
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

    public function actualizarCategoriaAdicional(Request $request, $id)
    {
        try {
            $categoria = CategoriaAdicional::findOrFail($id);
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'nombre' => 'required|string|max:255',
            ]);

            $categoria->update($request->all());
            return response()->json(['success' => true, 'data' => $categoria, 'message' => 'Categoría adicional actualizada con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Categoría adicional no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }

    public function eliminarCategoriaAdicional($id)
    {
        try {
            $categoria = CategoriaAdicional::findOrFail($id);
            $categoria->delete();
            return response()->json(['success' => true, 'message' => 'Categoría adicional eliminada con éxito']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Categoría adicional no encontrada'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar la categoría adicional', 'error' => $e->getMessage()], 500);
        }
    }
}