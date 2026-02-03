<?php

namespace App\Http\Controllers;

use App\Models\Adicional;
use App\Models\CategoriaMenu;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function store(Request $request)
    {
        // Guardar la imagen en el almacenamiento
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('menus', 'custom_public');
            $fotoUrl = Storage::url($fotoPath);
        }

        // Crear el menú con la ruta de la imagen
        $menu = Menu::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'foto' => $fotoUrl ?? null,
            'precio' => $request->precio,
            'status' => $request->status,
            'empresa_id' => $request->empresa_id,
        ]);

        // Asociar el menú con la categoría
        CategoriaMenu::create([
            'categoria_id' => $request->categoria_id,
            'menu_id' => $menu->id,
        ]);

        return response()->json($menu, 201);
    }

    public function index(Request $request, $empresa_id)
    {
        $categoriaFiltro = $request->query('categoria');

        if ($categoriaFiltro !== null) {
            $menuIds = CategoriaMenu::where('categoria_id', $categoriaFiltro)->pluck('menu_id')->toArray();
            $menus = Menu::where('empresa_id', $empresa_id)->whereIn('id', $menuIds)->get();
        } else {
            $menus = Menu::where('empresa_id', $empresa_id)->get();
        }

        $menusWithCategory = $menus->map(function ($menu) {
            $categoriaMenu = CategoriaMenu::where('menu_id', $menu->id)->first();
            $menuArray = $menu->toArray();
            $menuArray['categoria_id'] = $categoriaMenu ? $categoriaMenu->categoria_id : null;
            // Agregar cantidad de adicionales
            $menuArray['adicionales_count'] = Adicional::where('menu_id', $menu->id)->count();
            return $menuArray;
        });

        return response()->json($menusWithCategory);
    }

    public function updateStatus($id, Request $request)
    {
        $dish = Menu::find($id);

        if (!$dish) {
            return response()->json(['message' => 'Platillo no encontrado'], 404);
        }

        $dish->status = $request->status;
        $dish->save();

        return response()->json(['message' => 'Estado actualizado correctamente', 'dish' => $dish], 200);
    }

    public function getMenuCategoria($empresa_id)
    {
        $menus = Menu::where('empresa_id', $empresa_id)
            ->where('status', 'active')
            ->whereHas('categorias', function ($q) {
                $q->where('estado', 1);
            })
            ->with(['categorias' => function ($q) {
                $q->where('estado', 1);
            }])
            ->get();

        $groupedMenus = [];

        foreach ($menus as $menu) {
            foreach ($menu->categorias as $categoria) {
                $categoriaIndex = array_search($categoria->nombre, array_column($groupedMenus, 'nombre'));

                if ($categoriaIndex === false) {
                    $groupedMenus[] = [
                        'nombre' => $categoria->nombre,
                        'items' => []
                    ];
                    $categoriaIndex = array_key_last($groupedMenus);
                }

                $groupedMenus[$categoriaIndex]['items'][] = [
                    'id' => $menu->id,
                    'empresa_id' => $menu->empresa_id,
                    'titulo' => $menu->titulo,
                    'descripcion' => $menu->descripcion,
                    'foto' => $menu->foto,
                    'precio' => $menu->precio,
                    'status' => $menu->status
                ];
            }
        }

        return response()->json($groupedMenus);
    }

    public function destroy($id)
    {
        try {
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            // Eliminar la relación con categorías
            CategoriaMenu::where('menu_id', $id)->delete();

            // Eliminar adicionales asociados
            Adicional::where('menu_id', $id)->delete();

            // Eliminar la imagen si existe
            if ($dish->foto && Storage::exists($dish->foto)) {
                Storage::delete($dish->foto);
            }

            $dish->delete();

            return response()->json(['message' => 'Platillo eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar platillo', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            $dish->titulo = $request->titulo;
            $dish->descripcion = $request->descripcion;
            $dish->precio = $request->precio;
            $dish->status = $request->status;

            if ($request->hasFile('foto')) {
                if ($dish->foto && Storage::exists($dish->foto)) {
                    Storage::delete($dish->foto);
                }
                $fotoPath = $request->file('foto')->store('menus', 'custom_public');
                $dish->foto = Storage::url($fotoPath);
            }

            $dish->save();

            if ($request->has('categoria_id')) {
                CategoriaMenu::where('menu_id', $id)->delete();
                CategoriaMenu::create([
                    'categoria_id' => $request->categoria_id,
                    'menu_id' => $dish->id,
                ]);
            }

            return response()->json([
                'message' => 'Platillo actualizado correctamente',
                'dish' => $dish
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar platillo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMenusByCategory($categoria_id)
    {
        $menuIds = CategoriaMenu::where('categoria_id', $categoria_id)
            ->pluck('menu_id')
            ->toArray();

        $menus = Menu::whereIn('id', $menuIds)->get();

        return response()->json($menus);
    }

    /**
     * Obtener adicionales de una empresa o de un menú específico
     */
    public function getAdicionales(Request $request, $id)
    {
        $menuId = $request->query('menu_id');

        if ($menuId) {
            // Obtener adicionales de un menú específico
            $adicionales = Adicional::where('menu_id', $menuId)
                ->where('status', 'active')
                ->select('id', 'titulo', 'precio')
                ->get();

            return response()->json($adicionales);
        }

        // Comportamiento original: todos los adicionales de la empresa
        $adicionales = DB::select("SELECT
                    id,
                    titulo,
                    precio 
                FROM
                    `adicionales` 
                WHERE
                    adicionales.empresa_id = ? 
                    AND adicionales.`status` = 'active'", [$id]);

        return response()->json($adicionales);
    }

    /**
     * Obtener adicionales de un menú específico
     */
    public function getMenuAdicionales($menu_id)
    {
        $adicionales = Adicional::where('menu_id', $menu_id)
            ->where('status', 'active')
            ->get();

        return response()->json($adicionales);
    }

    /**
     * Crear adicional para un menú
     */
    public function createMenuAdicional(Request $request, $menu_id)
    {
        $menu = Menu::find($menu_id);

        if (!$menu) {
            return response()->json(['message' => 'Menú no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fotoUrl = '';
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
            $fotoUrl = Storage::url($fotoPath);
        }

        $adicional = Adicional::create([
            'menu_id' => $menu_id,
            'empresa_id' => $menu->empresa_id,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion ?? '',
            'foto' => $fotoUrl,
            'precio' => $request->precio,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Adicional creado correctamente',
            'adicional' => $adicional
        ], 201);
    }

    /**
     * Actualizar adicional de un menú
     */
    public function updateMenuAdicional(Request $request, $menu_id, $adicional_id)
    {
        $adicional = Adicional::where('id', $adicional_id)
            ->where('menu_id', $menu_id)
            ->first();

        if (!$adicional) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'status' => 'sometimes|in:active,inactive',
            'foto' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('foto')) {
            if ($adicional->foto) {
                Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
            }
            $fotoPath = $request->file('foto')->store('adicionales', 'custom_public');
            $adicional->foto = Storage::url($fotoPath);
        }

        $adicional->titulo = $request->titulo;
        $adicional->descripcion = $request->descripcion ?? '';
        $adicional->precio = $request->precio;
        if ($request->has('status')) {
            $adicional->status = $request->status;
        }
        $adicional->save();

        return response()->json([
            'message' => 'Adicional actualizado correctamente',
            'adicional' => $adicional
        ]);
    }

    /**
     * Eliminar adicional de un menú
     */
    public function deleteMenuAdicional($menu_id, $adicional_id)
    {
        $adicional = Adicional::where('id', $adicional_id)
            ->where('menu_id', $menu_id)
            ->first();

        if (!$adicional) {
            return response()->json(['message' => 'Adicional no encontrado'], 404);
        }

        if ($adicional->foto) {
            Storage::disk('custom_public')->delete(str_replace('/storage/', '', $adicional->foto));
        }

        $adicional->delete();

        return response()->json([
            'message' => 'Adicional eliminado correctamente'
        ]);
    }
}
