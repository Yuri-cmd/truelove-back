<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\ClienteDireccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalesController extends Controller
{
    private $pedidoService;
    private $negocioService;

    public function __construct(
        \App\Services\PedidoService $pedidoService,
        \App\Services\NegocioService $negocioService
    ) {
        $this->pedidoService = $pedidoService;
        $this->negocioService = $negocioService;
    }

    public function getLocalesTop($idCliente)
    {
        if ($idCliente == null) {
            $locales = $this->getLocalesCercanos(-12.171099966956891, -77.02171014857589, false, false, false);
        }
        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        if ($coordenadas && isset($coordenadas->coordinates)) {
            $lat = (double) $coordenadas->coordinates[0];
            $lng = (double) $coordenadas->coordinates[1];
        } else {
            $coords = explode(',', (string)($direccion->coordenadas ?? ''));
            if (count($coords) >= 2) {
                // Asumiendo formato antiguo [lat, lng] o similar
                $lat = (double) trim($coords[0]);
                $lng = (double) trim($coords[1]);
            } else {
                return response()->json(['error' => 'Formato de coordenadas inválido'], 400);
            }
        }

        $locales = $this->getLocalesCercanos($lng, $lat, false, false, false);

        return response()->json($locales);
    }

    public function getLocales($idCliente, $category = false)
    {

        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        if ($coordenadas && isset($coordenadas->coordinates)) {
            $lat = (double) $coordenadas->coordinates[0];
            $lng = (double) $coordenadas->coordinates[1];
        } else {
            $coords = explode(',', (string)($direccion->coordenadas ?? ''));
            if (count($coords) >= 2) {
                $lat = (double) trim($coords[0]);
                $lng = (double) trim($coords[1]);
            } else {
                return response()->json(['error' => 'Formato de coordenadas inválido'], 400);
            }
        }

        $locales = $this->getLocalesCercanos($lng, $lat, $category, false, false);

        return response()->json($locales);
    }

    public function searchLocales($idCliente, $term = false)
    {
        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        if ($coordenadas && isset($coordenadas->coordinates)) {
            $lat = (double) $coordenadas->coordinates[0];
            $lng = (double) $coordenadas->coordinates[1];
        } else {
            $coords = explode(',', (string)($direccion->coordenadas ?? ''));
            if (count($coords) >= 2) {
                $lat = (double) trim($coords[0]);
                $lng = (double) trim($coords[1]);
            } else {
                return response()->json(['error' => 'Formato de coordenadas inválido'], 400);
            }
        }

        $locales = $this->getLocalesCercanos($lng, $lat, false, $term, false);

        return response()->json($locales);
    }

    private function getLocalesCercanos($lat, $lng, $category = false, $term = false, $tieneLimite = true)
    {
        $radio = 10;
        $where = [];
        $limite = $tieneLimite ? 'LIMIT 10' : '';

        // Filtros dinámicos
        if ($category) {
            $where[] = "business_registrations.businessType = '$category'";
        }

        if ($term) {
            // Convierte el término de búsqueda a minúsculas una vez en PHP
            $lowerTerm = strtolower($term);

            // APLICA LOWER() EN EL CAMPO DE LA DB para búsqueda insensible a mayúsculas/minúsculas
            $where[] = "(LOWER(establecimientos.nombre_establecimiento) LIKE '%$lowerTerm%' OR
            LOWER(business_registrations.businessType) LIKE '%$lowerTerm%')";

        }

        /* $where[] = "(6371 * acos(
                     cos(radians($lat)) * cos(radians(establecimientos.latitud)) *
                     cos(radians(establecimientos.longitud) - radians($lng)) +
                     sin(radians($lat)) * sin(radians(establecimientos.latitud))
                 )) <= $radio";*/

        // Armamos el WHERE final
        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $query = DB::select("SELECT 
            business_registrations.id AS business_registration_id,
            establecimientos.nombre_establecimiento,
            perfiles_negocio.ruta_logo,
            establecimientos.calle,
            establecimientos.numero,
            establecimientos.codigo_postal,
            establecimientos.provincia,
            establecimientos.ciudad,
            establecimientos.referencia,
            establecimientos.latitud,
            establecimientos.longitud,
            establecimientos.direccion_completa,
            perfiles_negocio.banner,
            perfiles_negocio.foto_perfil,
            business_registrations.businessType,
            business_registrations.omitir_pago_adelantado,
            CASE WHEN business_registrations.activo = 1 THEN TRUE ELSE FALSE END AS activo,
            local_priorities.prioridad,
            (6371 * acos(
                cos(radians($lat)) * cos(radians(establecimientos.latitud)) *
                cos(radians(establecimientos.longitud) - radians($lng)) +
                sin(radians($lat)) * sin(radians(establecimientos.latitud))
            )) AS distancia
        FROM business_registrations
        INNER JOIN establecimientos 
            ON business_registrations.id = establecimientos.business_registration_id
        LEFT JOIN perfiles_negocio 
            ON business_registrations.id = perfiles_negocio.business_registration_id
        LEFT JOIN local_priorities 
            ON establecimientos.id = local_priorities.establecimiento_id
        $whereSql
        ORDER BY business_registrations.activo DESC, local_priorities.prioridad DESC, distancia ASC
        $limite");

        if (!empty($query)) {
            // Google Distance Matrix API permite hasta 25 destinos por solicitud en el plan estándar
            $batchSize = 25;
            $allGoogleDistances = [];
            
            $chunks = array_chunk($query, $batchSize);
            
            foreach ($chunks as $chunk) {
                $destinations = array_map(function($local) {
                    return [
                        'lat' => $local->latitud,
                        'lng' => $local->longitud
                    ];
                }, $chunk);

                $googleDistances = $this->pedidoService->obtenerDistanciaGoogle($lat, $lng, $destinations);
                $allGoogleDistances = array_merge($allGoogleDistances, $googleDistances);
            }

            foreach ($query as $index => $local) {
                if (isset($allGoogleDistances[$index]) && $allGoogleDistances[$index] !== null) {
                    $local->distancia = $allGoogleDistances[$index];
                }
            }

            // Re-ordenar por la nueva distancia real (Google)
            usort($query, function($a, $b) {
                // Primero por activo
                $actA = isset($a->activo) && $a->activo ? 1 : 0;
                $actB = isset($b->activo) && $b->activo ? 1 : 0;
                
                if ($actA != $actB) {
                    return $actB <=> $actA;
                }

                // Luego por prioridad (si existe y es diferente)
                $pA = isset($a->prioridad) ? (int)$a->prioridad : 0;
                $pB = isset($b->prioridad) ? (int)$b->prioridad : 0;
                
                if ($pA != $pB) {
                    return $pB <=> $pA;
                }
                
                // Luego por distancia real
                return $a->distancia <=> $b->distancia;
            });
        }

        return $query;
    }
}
