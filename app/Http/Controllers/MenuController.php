<?php

namespace App\Http\Controllers;

use App\Models\CategoriaMenu;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function store(Request $request)
    {
        // Guardar la imagen en el almacenamiento
        if ($request->hasFile('foto')) {
            // Se guarda la imagen en el disco público y se obtiene el path
            $fotoPath = $request->file('foto')->store('menus', 'custom_public');  // 'public/menus' es la carpeta donde se guardará
            // Obtiene la ruta relativa para almacenar en la base de datos (quitar "public/" para almacenar solo el nombre del archivo)
            $fotoUrl = Storage::url($fotoPath);  // Esto devuelve la URL pública de la imagen
        }

        // Crear el menú con la ruta de la imagen
        $menu = Menu::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'foto' => $fotoUrl ?? null,  // Solo guarda la URL de la imagen si fue cargada
            'precio' => $request->precio,
            'status' => $request->status,
            'empresa_id' => $request->empresa_id,  // Asegúrate de que el usuario tenga este campo en el request
        ]);

        // Asociar el menú con la categoría
        CategoriaMenu::create([
            'categoria_id' => $request->categoria_id,
            'menu_id' => $menu->id,
        ]);

        return response()->json($menu, 201);
    }

    public function index($empresa_id)
    {
        $menus = Menu::where('empresa_id', $empresa_id)->get();
        return response()->json($menus);
    }

    public function updateStatus($id, Request $request)
    {
        // Buscar el platillo por ID
        $dish = Menu::find($id);

        if (!$dish) {
            return response()->json(['message' => 'Platillo no encontrado'], 404);
        }

        // Actualizar el estado del platillo
        $dish->status = $request->status;
        $dish->save();

        // Retornar respuesta de éxito
        return response()->json(['message' => 'Estado actualizado correctamente', 'dish' => $dish], 200);
    }

    public function getMenuCategoria($empresa_id)
    {
        // Obtener todos los menús con sus categorías
        $menus = Menu::with('categorias')->where('empresa_id', $empresa_id)->where('status', 'active')->get();

        // Agrupar los menús por categoría
        $groupedMenus = [];

        foreach ($menus as $menu) {
            foreach ($menu->categorias as $categoria) {
                // Buscar si ya existe la categoría en el array agrupado
                $categoriaIndex = array_search($categoria->nombre, array_column($groupedMenus, 'nombre'));

                // Si la categoría no existe, se agrega
                if ($categoriaIndex === false) {
                    $groupedMenus[] = [
                        'nombre' => $categoria->nombre,
                        'items' => []
                    ];
                    $categoriaIndex = array_key_last($groupedMenus); // Obtener el índice recién agregado
                }

                // Agregar el menú a la categoría correspondiente
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
            // Buscar el platillo por ID
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            // Eliminar la relación con categorías
            CategoriaMenu::where('menu_id', $id)->delete();

            // Eliminar la imagen si existe
            if ($dish->foto && Storage::exists($dish->foto)) {
                Storage::delete($dish->foto);
            }

            // Eliminar el platillo
            $dish->delete();

            // Retornar respuesta de éxito
            return response()->json(['message' => 'Platillo eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar platillo', 'error' => $e->getMessage()], 500);
        }

    }
    public function update(Request $request, $id)
    {
        try {
            // Buscar el platillo por ID
            $dish = Menu::find($id);

            if (!$dish) {
                return response()->json(['message' => 'Platillo no encontrado'], 404);
            }

            // Actualizar los datos del platillo
            $dish->titulo = $request->titulo;
            $dish->descripcion = $request->descripcion;
            $dish->precio = $request->precio;
            $dish->status = $request->status;

            // Si se envía una nueva imagen
            if ($request->hasFile('foto')) {
                // Eliminar la imagen anterior si existe
                if ($dish->foto && Storage::exists($dish->foto)) {
                    Storage::delete($dish->foto);
                }

                // Guardar la nueva imagen
                $fotoPath = $request->file('foto')->store('menus', 'custom_public');
                $dish->foto = Storage::url($fotoPath);
            }

            // Guardar los cambios
            $dish->save();

            // Actualizar la categoría si es necesario
            if ($request->has('categoria_id')) {
                // Eliminar la relación anterior
                CategoriaMenu::where('menu_id', $id)->delete();

                // Crear la nueva relación
                CategoriaMenu::create([
                    'categoria_id' => $request->categoria_id,
                    'menu_id' => $dish->id,
                ]);
            }

            // Retornar respuesta de éxito
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
        // Obtener los IDs de los menús que pertenecen a esta categoría
        $menuIds = CategoriaMenu::where('categoria_id', $categoria_id)
            ->pluck('menu_id')
            ->toArray();

        // Obtener los menús con esos IDs
        $menus = Menu::whereIn('id', $menuIds)->get();

        return response()->json($menus);
    }



}