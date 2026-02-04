<?php

namespace App\Http\Controllers;

use App\Models\GrupoAdicional;
use App\Models\Adicional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrupoAdicionalController extends Controller
{
    /**
     * Listar todos los grupos de una empresa
     */
    public function index($empresa_id)
    {
        try {
            $grupos = GrupoAdicional::where('empresa_id', $empresa_id)
                ->with([
                    'items' => function ($query) {
                        $query->select('adicionales.id', 'adicionales.titulo', 'adicionales.precio')
                            ->orderBy('grupo_adicional_items.orden');
                    }
                ])
                ->orderBy('nombre')
                ->get();

            return response()->json($grupos);
        } catch (\Exception $e) {
            Log::error('Error al obtener grupos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener grupos'], 500);
        }
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'empresa_id' => 'required|exists:business_registrations,id',
                'nombre' => 'required|string|max:255',
                'minimo' => 'required|integer|min:0',
                'maximo' => 'required|integer|min:1',
            ]);

            $grupo = GrupoAdicional::create([
                'empresa_id' => $request->empresa_id,
                'nombre' => $request->nombre,
                'minimo' => $request->minimo,
                'maximo' => $request->maximo,
                'estado' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado correctamente',
                'data' => $grupo
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear grupo: ' . $e->getMessage());
            return response()->json(['error' => 'Error al crear grupo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mostrar un grupo específico con sus items
     */
    public function show($id)
    {
        try {
            $grupo = GrupoAdicional::with([
                'items' => function ($query) {
                    $query->orderBy('grupo_adicional_items.orden');
                }
            ])->findOrFail($id);

            return response()->json($grupo);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }
    }

    /**
     * Actualizar un grupo
     */
    public function update(Request $request, $id)
    {
        try {
            $grupo = GrupoAdicional::findOrFail($id);

            $request->validate([
                'nombre' => 'required|string|max:255',
                'minimo' => 'required|integer|min:0',
                'maximo' => 'required|integer|min:1',
                'estado' => 'sometimes|in:active,inactive',
            ]);

            $grupo->update([
                'nombre' => $request->nombre,
                'minimo' => $request->minimo,
                'maximo' => $request->maximo,
                'estado' => $request->estado ?? $grupo->estado,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado correctamente',
                'data' => $grupo
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar grupo: ' . $e->getMessage());
            return response()->json(['error' => 'Error al actualizar grupo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un grupo
     */
    public function destroy($id)
    {
        try {
            $grupo = GrupoAdicional::findOrFail($id);

            // Eliminar relaciones
            DB::table('grupo_adicional_items')->where('grupo_id', $id)->delete();
            DB::table('menu_grupos')->where('grupo_id', $id)->delete();

            $grupo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar grupo: ' . $e->getMessage());
            return response()->json(['error' => 'Error al eliminar grupo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Agregar un adicional a un grupo
     */
    public function addItem(Request $request, $grupo_id)
    {
        try {
            $request->validate([
                'adicional_id' => 'required|exists:adicionales,id',
                'precio' => 'required|numeric|min:0',
                'orden' => 'nullable|integer',
            ]);

            // Verificar que no exista ya
            $exists = DB::table('grupo_adicional_items')
                ->where('grupo_id', $grupo_id)
                ->where('adicional_id', $request->adicional_id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'El adicional ya está en este grupo'], 400);
            }

            $ultimoOrden = DB::table('grupo_adicional_items')
                ->where('grupo_id', $grupo_id)
                ->max('orden');

            $orden = ($ultimoOrden ?? 0) + 1;

            DB::table('grupo_adicional_items')->insert([
                'grupo_id' => $grupo_id,
                'adicional_id' => $request->adicional_id,
                'precio' => $request->precio,
                'orden' => $request->orden ?? $orden,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Adicional agregado al grupo'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al agregar item: ' . $e->getMessage());
            return response()->json(['error' => 'Error al agregar item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un item del grupo (precio, orden)
     */
    public function updateItem(Request $request, $grupo_id, $adicional_id)
    {
        try {
            $request->validate([
                'precio' => 'required|numeric|min:0',
                'orden' => 'nullable|integer',
            ]);

            DB::table('grupo_adicional_items')
                ->where('grupo_id', $grupo_id)
                ->where('adicional_id', $adicional_id)
                ->update([
                    'precio' => $request->precio,
                    'orden' => $request->orden ?? 0,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Item actualizado'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar item: ' . $e->getMessage());
            return response()->json(['error' => 'Error al actualizar item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remover un adicional de un grupo
     */
    public function removeItem($grupo_id, $adicional_id)
    {
        try {
            DB::table('grupo_adicional_items')
                ->where('grupo_id', $grupo_id)
                ->where('adicional_id', $adicional_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Adicional removido del grupo'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al remover item: ' . $e->getMessage());
            return response()->json(['error' => 'Error al remover item: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Asignar grupos a un menú/producto
     */
    public function assignToMenu(Request $request, $menu_id)
    {
        try {
            $request->validate([
                'grupos' => 'required|array',
                'grupos.*' => 'exists:grupo_adicionales,id',
            ]);

            // Eliminar asignaciones anteriores
            DB::table('menu_grupos')->where('menu_id', $menu_id)->delete();

            // Crear nuevas asignaciones
            foreach ($request->grupos as $orden => $grupo_id) {
                DB::table('menu_grupos')->insert([
                    'menu_id' => $menu_id,
                    'grupo_id' => $grupo_id,
                    'orden' => $orden,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Grupos asignados correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al asignar grupos: ' . $e->getMessage());
            return response()->json(['error' => 'Error al asignar grupos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener grupos asignados a un menú
     */
    public function getMenuGroups($menu_id)
    {
        try {
            $grupos = DB::table('menu_grupos')
                ->join('grupo_adicionales', 'menu_grupos.grupo_id', '=', 'grupo_adicionales.id')
                ->where('menu_grupos.menu_id', $menu_id)
                ->orderBy('menu_grupos.orden')
                ->select('grupo_adicionales.*', 'menu_grupos.orden')
                ->get();

            return response()->json($grupos);
        } catch (\Exception $e) {
            Log::error('Error al obtener grupos del menú: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener grupos'], 500);
        }
    }

    /**
     * Obtener adicionales disponibles para agregar a un grupo (de la misma empresa)
     */
    public function getAvailableItems($grupo_id)
    {
        try {
            $grupo = GrupoAdicional::findOrFail($grupo_id);

            // Obtener IDs de adicionales ya en el grupo
            $existingIds = DB::table('grupo_adicional_items')
                ->where('grupo_id', $grupo_id)
                ->pluck('adicional_id');

            // Obtener adicionales de la empresa que no están en el grupo
            $adicionales = Adicional::where('empresa_id', $grupo->empresa_id)
                ->where('status', 'active')
                ->whereNotIn('id', $existingIds)
                ->select('id', 'titulo', 'precio')
                ->get();

            return response()->json($adicionales);
        } catch (\Exception $e) {
            Log::error('Error al obtener adicionales disponibles: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener adicionales'], 500);
        }
    }
}
