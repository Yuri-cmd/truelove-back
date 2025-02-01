<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Pedido;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    // Método para obtener la ubicación del motorizado
    public function fetchMotorcycleLocation($idPedido)
    {
        $idMotorizado = Pedido::where('id', $idPedido)
            ->value('id_motorizado');

        $location = Location::where('motorizado_id', $idMotorizado)
            ->latest()
            ->first();  // Obtener la última ubicación del motorizado

        if ($location) {
            return response()->json([
                'lat' => $location->lat,
                'lon' => $location->lon,
            ]);
        } else {
            return response()->json([
                'error' => 'Ubicación no encontrada',
            ], 404);
        }
    }
}
