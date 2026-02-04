<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categorias;
use Illuminate\Support\Facades\Log;

class CategoriaController extends Controller
{
    public function index($empresa_id)
    {
        $categorias = Categorias::where('empresa_id', $empresa_id)->get();
        return response()->json($categorias);
    }
    //obtener categorias por empresa_id en la web
    public function obtenerCategories($empresa_id)
    {
        try {

            // Verificar que el usuario tenga acceso a este empresa_id
            if (auth()->user()->businessRegistration && auth()->user()->businessRegistration->id != $empresa_id) {
                return response()->json([
                    'error' => 'No autorizado',
                    'message' => 'No tiene permiso para ver estas categorías'
                ], 403);
            }

            $categorias = Categorias::where('empresa_id', $empresa_id)->get();

            return response()->json([
                'success' => true,
                'data' => $categorias,
                'count' => $categorias->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en CategoriaController@obtenerCategories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'empresa_id' => $empresa_id
            ]);

            return response()->json([
                'error' => 'Error al obtener categorías',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // 0. Depuración Rápida: Ver qué llega
        // Esto detendrá la ejecución y te mostrará el array "horarios" si está llegando bien.
        // Si sale null o vacío, el problema es el envío desde Flutter.
        // 1. Validar
        $request->validate([
            'nombre' => 'required|string|max:255',
            'empresa_id' => 'required',
            'horarios' => 'nullable|array', // Asegúrate que esto pase la validación
        ]);
        // 2. Crear
        // Simplifica esto para probar
        $category = Categorias::create($request->all());

        return response()->json($category, 201);
    }

    //crear categoria en la web
    public function crearCategoria(Request $request)
    {
        try {
            // Validaciones
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'empresa_id' => 'required|string',
                'hora_inicio' => 'nullable',
                'hora_fin' => 'nullable',
            ], [
                'nombre.required' => 'El nombre de la categoría es requerido',
                'empresa_id.required' => 'El ID de la empresa es requerido'
            ]);

            // Verificar que empresa_id no sea undefined
            if ($request->empresa_id === 'undefined') {
                throw new \Exception('ID de empresa no válido');
            }

            $category = Categorias::create([
                'nombre' => $validated['nombre'],
                'empresa_id' => $validated['empresa_id'],
                'hora_inicio' => $request->hora_inicio,
                'hora_fin' => $request->hora_fin,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoría creada exitosamente',
                'data' => $category
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en CategoriaController@crearCategoria', [
                'errors' => $e->errors()
            ]);

            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error en CategoriaController@crearCategoria', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Error al crear categoría',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    //actualizar categoria en la app
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'hora_inicio' => 'nullable',
            'hora_fin' => 'nullable',
        ]);

        $category = Categorias::findOrFail($id);
        $category->update([
            'nombre' => $request->nombre,
            'empresa_id' => $request->empresa_id,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
        ]);

        return response()->json($category, 200);
    }


    public function actualizarCategoria(Request $request, $id)
    {
        try {
            // Validaciones
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'empresa_id' => 'required|string',
                'hora_inicio' => 'nullable',
                'hora_fin' => 'nullable',
            ]);

            $category = Categorias::where('id', $id)
                ->where('empresa_id', $request->empresa_id)
                ->first();

            if (!$category) {
                return response()->json([
                    'error' => 'Categoría no encontrada',
                    'message' => 'La categoría no existe o no pertenece a esta empresa'
                ], 404);
            }

            $category->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Categoría actualizada exitosamente',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            Log::error('Error en CategoriaController@actualizarCategoria', [
                'error' => $e->getMessage(),
                'id' => $id,
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => 'Error al actualizar categoría',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Eliminar categoría
    public function destroy($id, $id_empresa)
    {
        $category = Categorias::where('id', $id)->where('empresa_id', $id_empresa)->firstOrFail();
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada con éxito'], 200);
    }
    //eliminar categoria en la web
    public function eliminarCategoria($id, $id_empresa)
    {
        try {
            if (!$id_empresa || $id_empresa === 'undefined') {
                throw new \Exception('ID de empresa no válido');
            }

            $category = Categorias::where('id', $id)
                ->where('empresa_id', $id_empresa)
                ->first();

            if (!$category) {
                return response()->json([
                    'error' => 'Categoría no encontrada',
                    'message' => 'La categoría no existe o no pertenece a esta empresa'
                ], 404);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en CategoriaController@destroy', [
                'error' => $e->getMessage(),
                'id' => $id,
                'id_empresa' => $id_empresa
            ]);

            return response()->json([
                'error' => 'Error al eliminar categoría',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Actualizar el estado (activo/inactivo) de una categoría
    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required',
            'empresa_id' => 'required'
        ]);

        $category = Categorias::where('id', $id)
            ->where('empresa_id', $request->empresa_id)
            ->first();

        if (!$category) {
            return response()->json([
                'error' => 'Categoría no encontrada',
                'message' => 'La categoría no existe o no pertenece a esta empresa'
            ], 404);
        }

        $category->estado = $request->estado;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado de categoría actualizado correctamente',
            'data' => $category
        ], 200);
    }
}