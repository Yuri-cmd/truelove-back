<?php

namespace App\Http\Controllers;

use App\Models\Favorito;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FavoritoController extends Controller
{
    /**
     * Marca/desmarca un producto como favorito para un cliente.
     * Body: { cliente_id, menu_id }
     */
    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'cliente_id' => 'required|integer|exists:clientes,id',
                'menu_id' => 'required|integer|exists:menu,id',
            ]);

            $favorito = Favorito::where('cliente_id', $request->cliente_id)
                ->where('menu_id', $request->menu_id)
                ->first();

            if ($favorito) {
                $favorito->delete();
                return response()->json(['success' => true, 'is_favorito' => false]);
            }

            Favorito::create([
                'cliente_id' => $request->cliente_id,
                'menu_id' => $request->menu_id,
            ]);

            return response()->json(['success' => true, 'is_favorito' => true]);
        } catch (\Exception $e) {
            Log::error('Error en FavoritoController@toggle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el favorito',
            ], 500);
        }
    }

    /**
     * IDs de menú favoriteados por el cliente dentro de una empresa/local
     * específico. Usado por la app para saber qué corazones marcar al abrir
     * el detalle de un restaurante.
     */
    public function porEmpresa($idCliente, $idEmpresa)
    {
        try {
            $menuIds = Favorito::where('cliente_id', $idCliente)
                ->whereIn('menu_id', Menu::where('empresa_id', $idEmpresa)->pluck('id'))
                ->pluck('menu_id');

            return response()->json(['success' => true, 'data' => $menuIds]);
        } catch (\Exception $e) {
            Log::error('Error en FavoritoController@porEmpresa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los favoritos',
            ], 500);
        }
    }

    /**
     * Productos favoritos del cliente con su información completa (para una
     * futura pantalla general de "Mis favoritos" si se necesita).
     */
    public function porCliente($idCliente)
    {
        try {
            $menus = Menu::whereIn(
                'id',
                Favorito::where('cliente_id', $idCliente)->pluck('menu_id')
            )->get();

            return response()->json(['success' => true, 'data' => $menus]);
        } catch (\Exception $e) {
            Log::error('Error en FavoritoController@porCliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los favoritos',
            ], 500);
        }
    }
}
