<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $rating = Rating::create([
            'id_pedido' => $request->id_pedido,
            'restaurant_rating' => $request->restaurant_rating,
            'restaurant_comment' => $request->restaurant_comment,
            'motorcycle_rating' => $request->motorcycle_rating,
            'motorcycle_comment' => $request->motorcycle_comment,
        ]);

        return response()->json([
            'message' => 'Calificación guardada con éxito',
            'rating' => $rating
        ], 201);
    }

    public function getRatings($id_pedido)
    {
        $ratings = Rating::where('id_pedido', $id_pedido)->get();
        return response()->json($ratings);
    }

    public function getRatingsBiker($id)
    {
        // Obtener los pedidos del motorizado
        $pedidos = Pedido::where('id_motorizado', $id)->get();
        // Obtener los ratings con los datos del cliente
        $data = $pedidos->map(function ($pedido) {
            $rating = Rating::where('id_pedido', $pedido->id)->first(); // Obtener el rating del pedido
            $cliente = Cliente::find($pedido->id_cliente); // Obtener el cliente
            return [
                'id_pedido' => $pedido->id,
                'cliente' => $cliente ? [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'telefono' => $cliente->telefono,
                ] : null, // Si no hay cliente, devolver null
                'rating' => $rating ? [
                    'motorcycle_rating' => $rating->motorcycle_rating,
                    'motorcycle_comment' => $rating->motorcycle_comment,
                ] : null, // Si no hay rating, devolver null
            ];
        });
        return response()->json($data);
    }
}
