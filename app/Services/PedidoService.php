<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\Location;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\RepartoRegistro;
use Illuminate\Support\Facades\Http;

class PedidoService
{
    protected $apiKey = '***MAPBOX_TOKEN_REMOVED***';

    // Método para calcular la distancia entre dos puntos usando la fórmula de Haversine
    public function calcularDistanciaHaversine($lat1, $lon1, $lat2, $lon2)
    {
        $radioTierra = 6371;  // Radio de la Tierra en km

        // Convertir grados a radianes
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // Diferencias entre las coordenadas
        $dLat = $lat2 - $lat1;
        $dLon = $lon2 - $lon1;

        // Fórmula de Haversine
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos($lat1) * cos($lat2) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distancia en km
        $distancia = $radioTierra * $c;

        return $distancia;
    }

    public function obtenerDistancia($lat1, $lon1, $lat2, $lon2)
    {
        $url = "https://api.mapbox.com/directions/v5/mapbox/driving/{$lon1},{$lat1};{$lon2},{$lat2}?access_token={$this->apiKey}&geometries=geojson";

        $response = Http::get($url);
        $data = $response->json();

        if (isset($data['routes'][0]['distance'])) {
            return $data['routes'][0]['distance'] / 1000; // en kilómetros
        }

        return null;
    }

    public function calcularPrecioPorDistancia($distanciaKm)
    {
        $precioBase = 4;
        $precioMax = 10;
        $distanciaMax = 10; // km

        if ($distanciaKm <= 1) {
            return $precioBase;
        }

        if ($distanciaKm >= $distanciaMax) {
            return $precioMax;
        }

        // Tarifa proporcional entre base y máxima
        $precio = $precioBase + (($precioMax - $precioBase) / ($distanciaMax - 1)) * ($distanciaKm - 1);
        return floor($precio * 100) / 100;
    }
    
    // Método para obtener el tiempo estimado de llegada desde el motorizado al local
    public function obtenerTiempoEstimadoTresPuntos($lat1, $lon1, $lat2, $lon2, $lat3, $lon3)
    {
        // Definir los puntos A (motorizado), B (local) y C (cliente)
        $start = [$lon1, $lat1];  // Punto A: Ubicación del motorizado
        $end = [$lon2, $lat2, $lon3, $lat3];  // Puntos B (local) y C (cliente)

        // URL para la API Directions de Mapbox con tres puntos
        $url = "https://api.mapbox.com/directions/v5/mapbox/driving/" . implode(',', $start) . ";" . implode(',', $end) . "?access_token={$this->apiKey}&geometries=geojson";

        // Realizar la solicitud GET a la API Directions de Mapbox
        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['routes'][0]['duration'])) {
                // La duración es en segundos, la convertimos a minutos
                return round($data['routes'][0]['duration'] / 60); // De segundos a minutos
            }
        }

        return null;  // Si la solicitud no fue exitosa o no hay duración
    }

    // Método para obtener el listado de pedidos y calcular el tiempo estimado
    public function obtenerPedidosCercanos()
    {
        $motorizados = RepartoRegistro::where('estado', 1)->where('aprobado', 1)->get();
        $tokens = [];
        foreach ($motorizados as $motorizado) {
            // Obtener la ubicación del motorizado
            $motorizadoLocation = Location::where('motorizado_id', $motorizado->id)
                ->latest()
                ->first();
            if ($motorizadoLocation) {
                // Obtener los pedidos con el id_local correspondiente
                $pedidos = Pedido::whereNotNull('id_local')->whereNull('id_motorizado')->get();

                foreach ($pedidos as $pedido) {
                    // Obtener el establecimiento asociado al pedido
                    $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();

                    if ($motorizadoLocation && $local) {
                        // Calcular la distancia entre el motorizado y el local
                        $distancia = $this->calcularDistanciaHaversine(
                            $motorizadoLocation->latitude,
                            $motorizadoLocation->longitude,
                            $local->latitud,
                            $local->longitud
                        );
                        // Verificar si el motorizado está dentro del radio de 10 km
                        if ($distancia <= 10) {
                            $tokens[] = $motorizado->token_fmc;
                        }
                    }
                }
            }
        }
        return array_unique($tokens);
    }
}
