<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\KilometrosTarifa;
use App\Models\TarifaRango;
use App\Models\Location;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\RepartoRegistro;
use Illuminate\Support\Facades\Http;

class PedidoService
{
    protected $apiKey;
    protected $googleApiKey;

    public function __construct()
    {
        $this->apiKey = env('MAPBOX_ACCESS_TOKEN');
        $this->googleApiKey = env('GOOGLE_MAPS_API_KEY');
    }

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

    public function obtenerDistanciaGoogle($lat1, $lon1, $destinations)
    {
        // Usa Mapbox Directions API (ruta real por carretera, no línea recta)
        // $destinations debe ser un array de arrays [['lat' => ..., 'lng' => ...], ...]
        if (empty($destinations)) return [];

        $distances = [];
        foreach ($destinations as $d) {
            $dist = $this->obtenerDistancia($lat1, $lon1, $d['lat'], $d['lng']);
            $distances[] = $dist;
        }

        return $distances;
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

    public function calcularPrecioPorDistancia($distanciaKm, $idLocal = null)
    {
        // Si hay local, buscar si tiene config propia (rangos o precio_por_km)
        if ($idLocal) {
            $configLocal = KilometrosTarifa::where('business_registration_id', $idLocal)
                ->where('activo', true)
                ->first();

            if ($configLocal) {
                $horaActual = \Carbon\Carbon::now('America/Lima')->format('H:i:s');

                if ($configLocal->modo_tarifa === 'precio_por_km') {
                    return $this->calcularPrecioPorKm($distanciaKm, $configLocal, $horaActual);
                }

                // Modo rangos
                $rango = TarifaRango::where('kilometros_tarifa_id', $configLocal->id)
                    ->where('distancia_desde', '<=', $distanciaKm)
                    ->where(function($query) use ($distanciaKm) {
                        $query->where('distancia_hasta', '>=', $distanciaKm)
                              ->orWhereNull('distancia_hasta');
                    })
                    ->orderBy('orden')
                    ->first();

                if (!$rango) return 5.00;

                $esNocturno = $this->esHorarioNocturno($horaActual, $configLocal->hora_inicio_nocturno, $configLocal->hora_fin_nocturno);
                return $esNocturno ? $rango->precio_nocturno : $rango->precio_diurno;
            }
        }

        // Sin config propia → usar config GLOBAL con cálculo proporcional original
        return $this->calcularPrecioGlobal($distanciaKm);
    }

    private function calcularPrecioGlobal($distanciaKm)
    {
        $hora = (int) \Carbon\Carbon::now('America/Lima')->format('G');
        $esNocturno = ($hora >= 23 || $hora < 5);

        $config = KilometrosTarifa::whereNull('business_registration_id')
            ->where('activo', true)
            ->first();

        if (!$config) {
            $precioBase = $esNocturno ? 5.50 : 4.00;
            $precioMax  = 10.00;
            $distanciaMax = 10.00;
            $distanciaMin = 1.00;
        } else {
            $precioBase   = $esNocturno ? (float) $config->precio_base_nocturno : (float) $config->precio_base_diurno;
            $precioMax    = (float) $config->precio_maximo;
            $distanciaMax = (float) $config->distancia_maxima;
            $distanciaMin = (float) $config->distancia_minima;
        }

        $epsilon = 0.0001;

        if ($distanciaKm <= $distanciaMin + $epsilon) return $precioBase;
        if ($distanciaKm >= $distanciaMax - $epsilon) return $precioMax;

        // Cálculo proporcional, redondeado al 0.50 más cercano
        $precio = $precioBase + (($precioMax - $precioBase) / ($distanciaMax - $distanciaMin)) * ($distanciaKm - $distanciaMin);
        return round($precio * 2) / 2;
    }

    private function calcularPrecioPorKm($distanciaKm, $config, $horaActual)
    {
        $esNocturno = $this->esHorarioNocturno($horaActual, $config->hora_inicio_nocturno, $config->hora_fin_nocturno);
        $precioBase = $esNocturno ? (float) $config->precio_base_nocturno : (float) $config->precio_base_diurno;
        $precioPorKm = $esNocturno ? (float) $config->precio_por_km_nocturno : (float) $config->precio_por_km_diurno;
        $precioMaximo = $config->precio_maximo ? (float) $config->precio_maximo : 25.00;
        $calculado = $precioBase + ($distanciaKm * $precioPorKm);
        $limitado = min($calculado, $precioMaximo);
        return round($limitado * 2) / 2;
    }

    /**
     * Determinar si una hora está en el rango nocturno
     */
    private function esHorarioNocturno($horaActual, $horaInicio, $horaFin)
    {
        // Convertir a objetos Carbon para comparar
        $actual = \Carbon\Carbon::createFromFormat('H:i:s', $horaActual);
        $inicio = \Carbon\Carbon::createFromFormat('H:i:s', $horaInicio);
        $fin = \Carbon\Carbon::createFromFormat('H:i:s', $horaFin);

        // Si el rango cruza la medianoche (ej: 23:00 - 05:00)
        if ($inicio->greaterThan($fin)) {
            return $actual->greaterThanOrEqualTo($inicio) || $actual->lessThanOrEqualTo($fin);
        }

        // Rango normal (ej: 19:00 - 23:59)
        return $actual->between($inicio, $fin);
    }

    /**
     * Método legacy (antiguo) por si no hay configuración de rangos
     * Se mantiene como fallback
     */
    private function calcularPrecioPorDistanciaLegacy($distanciaKm)
    {
        $hora = (int) \Carbon\Carbon::now('America/Lima')->format('G');
        $esNocturno = ($hora >= 23 || $hora < 5);

        $config = KilometrosTarifa::getConfiguracionActiva();
        
        if (!$config) {
            $precioBase = $esNocturno ? 5.50 : 4.00;
            $precioPorKm = $esNocturno ? 1.00 : 0.80;
            $precioMaximo = 25.00;
        } else {
            $precioBase = $esNocturno ? $config->precio_base_nocturno : $config->precio_base_diurno;
            $precioPorKm = $esNocturno ? $config->precio_por_km_nocturno : $config->precio_por_km_diurno;
            $precioMaximo = $config->precio_maximo ?? 25.00;
        }

        $precioCalculado = $precioBase + ($distanciaKm * $precioPorKm);

        if ($precioMaximo && $precioCalculado > $precioMaximo) {
            $precioCalculado = $precioMaximo;
        }

        return round($precioCalculado, 2);
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
        $notificar = [];
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
                        if ($distancia <= 10) { // Opcional: agregué la condición que parecía faltar o estar implícita
                            $notificar[$motorizado->id] = [
                                'token' => $motorizado->token_fmc,
                                'id' => $motorizado->id
                            ];
                        }
                    }
                }
            }
        }
        return array_values($notificar);
    }
}
