<?php

namespace App\Http\Controllers;

use App\Models\TipoNegocio;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TipoNegocioController extends Controller
{
    // Método para la app (solo activos, limitado)
    public function getAll()
    {
        return response()->json(TipoNegocio::where('activo', 1)->take(6)->get());
    }

    // ========== MÉTODOS DE ADMINISTRACIÓN - TIPOS DE NEGOCIO ==========

    /**
     * Obtener todos los tipos de negocio (activos e inactivos) para admin
     */
    public function getAllAdmin()
    {
        try {
            $tipos = TipoNegocio::withCount('categorias')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $tipos
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener tipos de negocio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los tipos de negocio'
            ], 500);
        }
    }

    /**
     * Crear un nuevo tipo de negocio
     */
    public function storeAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255|unique:tipos_negocios,nombre',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $slug = Str::slug($request->nombre);

            // Verificar que el slug sea único
            $counter = 1;
            $originalSlug = $slug;
            while (TipoNegocio::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $tipoNegocio = TipoNegocio::create([
                'nombre' => $request->nombre,
                'slug' => $slug,
                'activo' => true
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de negocio creado exitosamente',
                'data' => $tipoNegocio
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear tipo de negocio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el tipo de negocio'
            ], 500);
        }
    }

    /**
     * Actualizar un tipo de negocio
     */
    public function updateAdmin(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255|unique:tipos_negocios,nombre,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $tipoNegocio = TipoNegocio::findOrFail($id);

            $slug = Str::slug($request->nombre);

            // Verificar que el slug sea único (excluyendo el registro actual)
            $counter = 1;
            $originalSlug = $slug;
            while (TipoNegocio::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $tipoNegocio->update([
                'nombre' => $request->nombre,
                'slug' => $slug
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de negocio actualizado exitosamente',
                'data' => $tipoNegocio
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar tipo de negocio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el tipo de negocio'
            ], 500);
        }
    }

    /**
     * Eliminar un tipo de negocio
     */
    public function destroyAdmin($id)
    {
        DB::beginTransaction();

        try {
            $tipoNegocio = TipoNegocio::findOrFail($id);

            // Verificar si tiene categorías asociadas
            if ($tipoNegocio->categorias()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar el tipo de negocio porque tiene categorías asociadas'
                ], 400);
            }

            // Verificar si hay negocios usando este tipo
            if ($tipoNegocio->negocios()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar el tipo de negocio porque hay negocios registrados con este tipo'
                ], 400);
            }

            $tipoNegocio->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Tipo de negocio eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar tipo de negocio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el tipo de negocio'
            ], 500);
        }
    }

    /**
     * Activar/Desactivar un tipo de negocio
     */
    public function toggleStatus($id)
    {
        DB::beginTransaction();

        try {
            $tipoNegocio = TipoNegocio::findOrFail($id);
            $tipoNegocio->activo = !$tipoNegocio->activo;
            $tipoNegocio->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado exitosamente',
                'data' => $tipoNegocio
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar el estado'
            ], 500);
        }
    }

    // ========== MÉTODOS DE ADMINISTRACIÓN - CATEGORÍAS ==========

    /**
     * Obtener todas las categorías de un tipo de negocio para admin
     */
    public function getCategoriasAdmin($tipoNegocioId)
    {
        try {
            $categorias = Categoria::where('tipo_negocio_id', $tipoNegocioId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $categorias
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener categorías: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las categorías'
            ], 500);
        }
    }

    /**
     * Crear una nueva categoría
     */
    public function storeCategoria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255',
            'tipo_negocio_id' => 'required|exists:tipos_negocios,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $slug = Str::slug($request->nombre);

            // Verificar que el slug sea único
            $counter = 1;
            $originalSlug = $slug;
            while (Categoria::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $categoria = Categoria::create([
                'nombre' => $request->nombre,
                'slug' => $slug,
                'tipo_negocio_id' => $request->tipo_negocio_id,
                'activo' => true
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría creada exitosamente',
                'data' => $categoria
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear categoría: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear la categoría'
            ], 500);
        }
    }

    /**
     * Actualizar una categoría
     */
    public function updateCategoria(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:2|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $categoria = Categoria::findOrFail($id);

            $slug = Str::slug($request->nombre);

            // Verificar que el slug sea único (excluyendo el registro actual)
            $counter = 1;
            $originalSlug = $slug;
            while (Categoria::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            $categoria->update([
                'nombre' => $request->nombre,
                'slug' => $slug
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar categoría: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la categoría'
            ], 500);
        }
    }

    /**
     * Eliminar una categoría
     */
    public function destroyCategoria($id)
    {
        DB::beginTransaction();

        try {
            $categoria = Categoria::findOrFail($id);

            // Verificar si hay negocios usando esta categoría
            if ($categoria->negocios()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar la categoría porque hay negocios registrados con esta categoría'
                ], 400);
            }

            $categoria->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Categoría eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar categoría: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar la categoría'
            ], 500);
        }
    }

    /**
     * Activar/Desactivar una categoría
     */
    public function toggleCategoriaStatus($id)
    {
        DB::beginTransaction();

        try {
            $categoria = Categoria::findOrFail($id);
            $categoria->activo = !$categoria->activo;
            $categoria->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado exitosamente',
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar el estado'
            ], 500);
        }
    }
}
