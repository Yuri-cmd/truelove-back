<?php

namespace App\Http\Controllers;

use App\Models\PedidoTracking;
use Illuminate\Http\Request;

class PedidoTrackingController extends Controller
{
    public function obtenerEstado($id)
    {
        $pedido = PedidoTracking::where('pedido_id', $id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'id' => $pedido->id,
            'estado' => $pedido->estado, // Suponiendo que tienes un campo `estado`
        ]);
    }
}
