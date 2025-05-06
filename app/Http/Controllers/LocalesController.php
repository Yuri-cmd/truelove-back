<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\ClienteDireccion;
use Illuminate\Http\Request;

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
        $query = BusinessRegistration::with(['establecimiento', 'perfil'])
            ->selectRaw("
                *,
                perfiles_negocio.ruta_logo, 
                (6371 * acos(cos(radians(?)) * cos(radians(establecimientos.latitud)) * 
                cos(radians(establecimientos.longitud) - radians(?)) + 
                sin(radians(?)) * sin(radians(establecimientos.latitud)))) AS distancia", [$lat, $lng, $lat])
            ->join('establecimientos', 'business_registrations.id', '=', 'establecimientos.business_registration_id')
            ->join('perfiles_negocio', 'business_registrations.id', '=', 'perfiles_negocio.business_registration_id')
            ->leftJoin('local_priorities', 'establecimientos.id', '=', 'local_priorities.establecimiento_id');

        if ($category) {
            $query->where('business_registrations.businessType', $category);
        }

        if ($term) {
            $query->where('establecimientos.nombre_establecimiento', 'like', '%' . $term . '%');
        }

        $query->having('distancia', '<=', $radio)
            ->orderBy('local_priorities.prioridad', 'asc')
            ->orderBy('distancia', 'asc');
        if ($category) {
            $query->take(10);
        }
        $locales =  $query->get();
        return $locales;
    }
}
