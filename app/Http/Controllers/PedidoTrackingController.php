<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoTracking;
use Illuminate\Http\Request;

class PedidoTrackingController extends Controller
{
    public function obtenerEstado($id)
    {
        $pedido = PedidoTracking::where('pedido_id', $id)->latest()->first();
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }
        $tiempo = Pedido::find($id)->tiempo;

        return response()->json([
            'id' => $id,
            'estado' => $pedido->estado,
            'tiempo' => $tiempo ?? 0,
        ]);
    }

    public function updateEstado(Request $request)
    {
        // Buscar el pedido por su ID
        $pedido = Pedido::find($request->id);
        // Verificar si el pedido existe
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }
        // Registrar el tracking del pedido
        PedidoTracking::create([
            'pedido_id' => $pedido->id,
            'estado' => $request->estado
        ]);

        return response()->json(['status' => 'success']);
    }
}
