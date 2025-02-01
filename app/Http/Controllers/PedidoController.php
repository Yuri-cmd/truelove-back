<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;

class PedidoController extends Controller
{
    public function store(Request $request)
    {
        $pedido = Pedido::create($request->only(['id_local', 'id_cliente', 'latitud', 'longitud']));

        foreach ($request->items as $item) {
            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'nombre' => $item['name'],
                'cantidad' => $item['quantity'] ?? 1,
                'precio' => preg_replace('/[^\d.]/', '', $item['price']),
                'tipo' => 'item',
            ]);
        }

        foreach ($request->adicionales as $adicional) {
            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'nombre' => $adicional['name'],
                'cantidad' => 1,
                'precio' => preg_replace('/[^\d.]/', '', $adicional['price']),
                'tipo' => 'adicional',
            ]);
        }

        PedidoTracking::create(['pedido_id' => $pedido->id, 'estado' => 1]);

        return response()->json(['status' => 'success', 'pedido_id' => $pedido->id]);
    }
}
