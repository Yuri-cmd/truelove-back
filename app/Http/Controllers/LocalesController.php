<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\ClienteDireccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocalesController extends Controller
{
    public function getLocalesTop($idCliente)
    {
        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        $lat = $coordenadas->coordinates[0];
        $lng = $coordenadas->coordinates[1];

        $locales = $this->getLocalesCercanos($lng, $lat);

        return response()->json($locales);
    }

    public function getLocales($idCliente, $category = false)
    {

        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        $lat = $coordenadas->coordinates[0];
        $lng = $coordenadas->coordinates[1];

        $locales = $this->getLocalesCercanos($lng, $lat, $category);

        return response()->json($locales);
    }

    public function searchLocales($idCliente, $term = false)
    {
        $direccion = ClienteDireccion::where('id_cliente', $idCliente)->first();

        if (!$direccion || !$direccion->coordenadas) {
            return response()->json(['error' => 'Dirección no encontrada'], 404);
        }

        $coordenadas = json_decode($direccion->coordenadas);
        $lat = $coordenadas->coordinates[0];
        $lng = $coordenadas->coordinates[1];

        $locales = $this->getLocalesCercanos($lat, $lng, false, $term);

        return response()->json($locales);
    }

    private function getLocalesCercanos($lat, $lng, $category = false, $term = false)
    {
        $radio = 10;
        $where = [];

        // Filtros dinámicos
        if ($category) {
            $where[] = "business_registrations.businessType = '$category'";
        }

        if ($term) {
            // Convierte el término de búsqueda a minúsculas una vez en PHP
            $lowerTerm = strtolower($term);

            // APLICA LOWER() EN EL CAMPO DE LA DB para búsqueda insensible a mayúsculas/minúsculas
            $where[] = "LOWER(establecimientos.nombre_establecimiento) LIKE '%$lowerTerm%'";
        }

        // Condición para la distancia (filtrada antes del LIMIT)
        $where[] = "(6371 * acos(
                    cos(radians($lat)) * cos(radians(establecimientos.latitud)) *
                    cos(radians(establecimientos.longitud) - radians($lng)) +
                    sin(radians($lat)) * sin(radians(establecimientos.latitud))
                )) <= $radio";

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
        ORDER BY local_priorities.prioridad DESC, distancia ASC
        LIMIT 10");

        return $query;
    }
}
