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
            $fotoPath = $request->file('foto')->store('public/menus');  // 'public/menus' es la carpeta donde se guardará

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
}