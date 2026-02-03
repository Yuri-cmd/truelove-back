<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\KilometrosTarifa;
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

        \Log::debug('Mapbox directions response', [
            'url' => $url,
            'response' => $data
        ]);

        if (isset($data['routes'][0]['distance'])) {
            return $data['routes'][0]['distance'] / 1000; // en kilómetros
        }

        return null;
    }

    public function calcularPrecioPorDistancia($distanciaKm)
    {
        $hora = (int) \Carbon\Carbon::now('America/Lima')->format('G');

        $config = KilometrosTarifa::getConfiguracionActiva();
        if (!$config) {
            if ($hora >= 23 || $hora < 5) {
                $precioBase = 5.50;
            } else {
                $precioBase = 4.00;
            }
            $precioMax = 10.00;
            $distanciaMax = 10.00;
            $distanciaMin = 1.00;
        } else {
            if ($hora >= 23 || $hora < 5) {
                $precioBase = $config->precio_base_nocturno;
            } else {
                $precioBase = $config->precio_base_diurno;
            }
            $precioMax = $config->precio_maximo;
            $distanciaMax = $config->distancia_maxima;
            $distanciaMin = $config->distancia_minima;
        }

        // Tolerancia para evitar que 1.0000001 pase a la siguiente categoría
        $epsilon = 0.0001;

        if ($distanciaKm <= $distanciaMin + $epsilon)
            return $precioBase;
        if ($distanciaKm >= $distanciaMax - $epsilon)
            return $precioMax;

        // Cálculo proporcional
        $precio = $precioBase + (($precioMax - $precioBase) / ($distanciaMax - $distanciaMin)) * ($distanciaKm - $distanciaMin);

        // Si quieres redondear al .5 más cercano (en lugar de truncar hacia abajo), usar round()
        // return round($precio * 2) / 2;
        // Si quieres mantener comportamiento actual (siempre hacia abajo), conserva floor:
        return floor($precio * 2) / 2;
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
        $motorizados = RepartoRegistro::where('estado', 1)->where('aprobado', 1)->where('activo', 1)->get();
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
                            $tokens[] = $motorizado->token_fmc;
                    }
                }
            }
        }
        return array_unique($tokens);
    }
}
