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
        // Validación de los campos
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:1000',
            'foto' => 'required|url',
            'precio' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive,out-of-stock',
            'categoria_id' => 'required|exists:categoria,id', // Verifica que la categoría exista
        ]);

        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

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

        // Redirigir con un mensaje de éxito
        return response()->json('success', 'Menú creado y asociado correctamente.');
    }
}
