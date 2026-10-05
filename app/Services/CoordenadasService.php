<?php

namespace App\Services;

use App\Models\ClienteDireccion;

/**
 * Normaliza las coordenadas de las direcciones de los clientes.
 *
 * `clientes_direcciones.coordenadas` debería guardarse como GeoJSON
 * {"coordinates":[lng,lat]}, pero algunas apps lo guardan como [lat,lng] (y el
 * formato antiguo era un texto "a,b" sin orden definido). Como en Perú la
 * latitud (-19..0) y la longitud (-82..-68) nunca se solapan, el orden real se
 * deduce siempre por el valor y no por la posición.
 */
class CoordenadasService
{
    private const LAT_MIN = -19.0;
    private const LAT_MAX = 0.0;
    private const LNG_MIN = -82.0;
    private const LNG_MAX = -68.0;

    public function latitudEnPeru(float $valor): bool
    {
        return $valor >= self::LAT_MIN && $valor <= self::LAT_MAX;
    }

    public function longitudEnPeru(float $valor): bool
    {
        return $valor >= self::LNG_MIN && $valor <= self::LNG_MAX;
    }

    public function enPeru(float $lat, float $lng): bool
    {
        return $this->latitudEnPeru($lat) && $this->longitudEnPeru($lng);
    }

    /**
     * Ordena un par de valores recibidos en cualquier orden.
     *
     * @return array{lat: float, lng: float, valida: bool}
     *         `valida` es false si ningún orden cae en Perú (dato basura); en
     *         ese caso se asume el orden GeoJSON [lng, lat].
     */
    public function ordenar(float $a, float $b): array
    {
        if ($this->enPeru($b, $a)) {
            return ['lat' => $b, 'lng' => $a, 'valida' => true];  // [lng, lat]
        }
        if ($this->enPeru($a, $b)) {
            return ['lat' => $a, 'lng' => $b, 'valida' => true];  // [lat, lng]
        }

        return ['lat' => $b, 'lng' => $a, 'valida' => false];
    }

    /**
     * Lee coordenadas de una dirección de cliente (modelo), del JSON guardado
     * ({"coordinates":[a,b]}), de un texto "a,b" o de un arreglo [a,b].
     *
     * @return array{lat: float, lng: float, valida: bool}|null  null si no se puede leer
     */
    public function desdeDireccion(mixed $origen): ?array
    {
        if ($origen instanceof ClienteDireccion) {
            $origen = $origen->coordenadas;
        }

        $par = null;
        if (is_array($origen)) {
            $par = $origen['coordinates'] ?? $origen;
        } elseif (is_object($origen)) {
            $par = $origen->coordinates ?? null;
        } elseif (is_string($origen) && $origen !== '') {
            $json = json_decode($origen, true);
            if (is_array($json)) {
                $par = $json['coordinates'] ?? $json;
            } else {
                $par = explode(',', $origen);
            }
        }

        if (!is_array($par) || count($par) < 2 || !is_numeric($par[0]) || !is_numeric($par[1])) {
            return null;
        }

        return $this->ordenar((float) $par[0], (float) $par[1]);
    }

    /**
     * Devuelve el JSON GeoJSON {"coordinates":[lng,lat]} para guardar, o el
     * original si no se puede interpretar.
     */
    public function geoJson(mixed $origen): mixed
    {
        $c = $this->desdeDireccion($origen);
        $datos = is_string($origen) ? json_decode($origen, true) : (array) $origen;
        if (!$c || !$c['valida'] || !is_array($datos)) {
            return is_string($origen) ? $origen : json_encode($origen);
        }

        $datos['coordinates'] = [$c['lng'], $c['lat']];
        return json_encode($datos);
    }
}
