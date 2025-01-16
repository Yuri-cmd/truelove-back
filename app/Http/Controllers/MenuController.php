<?php

namespace App\Http\Controllers;

use App\Models\CategoriaMenu;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function store(Request $request)
    {
        // Crear el menú
        $menu = Menu::create([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'foto' => $request->foto,
            'precio' => $request->precio,
            'status' => $request->status,
            'empresa_id' => $request->empresa_id,  // Asume que el usuario tiene un campo `empresa_id`
        ]);

        // Asociar el menú con la categoría a través de la tabla `categoria_menu`
        CategoriaMenu::create([
            'categoria_id' => $request->categoria_id,
            'menu_id' => $menu->id,
        ]);

        return response()->json($menu, 201);
    }
}
