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
        return response()->json(TipoNegocio::where('activo', 1)->orderBy('orden')->take(10)->get());
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
            'orden' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // máximo 2MB
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

            $data = [
                'nombre' => $request->nombre,
                'slug' => $slug,
                'orden' => $request->orden,
                'activo' => true
            ];

            // Manejar la subida de imagen
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = Str::slug($request->nombre) . '.' . $image->getClientOriginalExtension();

                // Guardar en public/img/categories
                $image->move(public_path('img/categories'), $imageName);

                // Guardar la ruta completa en la DB
                $data['image'] = '/img/categories/' . $imageName;
            }

            $tipoNegocio = TipoNegocio::create($data);

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
            'orden' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // máximo 2MB
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

            $data = [
                'nombre' => $request->nombre,
                'slug' => $slug,
                'orden' => $request->orden
            ];

            // Manejar la subida de imagen
            if ($request->hasFile('image')) {
                // Eliminar la imagen anterior si existe
                if ($tipoNegocio->image) {
                    // La imagen en DB tiene el formato /img/categories/nombre.png
                    $oldImagePath = public_path(ltrim($tipoNegocio->image, '/'));
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $image = $request->file('image');
                $imageName = Str::slug($request->nombre) . '.' . $image->getClientOriginalExtension();

                // Guardar en public/img/categories
                $image->move(public_path('img/categories'), $imageName);

                // Guardar la ruta completa en la DB
                $data['image'] = '/img/categories/' . $imageName;
            }

            $tipoNegocio->update($data);

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
