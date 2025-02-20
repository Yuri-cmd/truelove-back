<?php

namespace App\Http\Controllers;

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
}
