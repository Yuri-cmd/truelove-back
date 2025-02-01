<?php

use App\Models\Establecimiento;
use App\Models\Location;
use App\Models\Pedido;
use GuzzleHttp\Client;

class PedidoService
{
    protected $apiKey = '***MAPBOX_TOKEN_REMOVED***';  // Reemplaza con tu clave API de Mapbox

    // Método para obtener el tiempo estimado de llegada desde el motorizado al local
    public function obtenerTiempoEstimado($lat1, $lon1, $lat2, $lon2)
    {
        $client = new Client();
        
        // URL para la API Directions de Mapbox
        $url = "https://api.mapbox.com/directions/v5/mapbox/driving/$lon1,$lat1;$lon2,$lat2?access_token=$this->apiKey&alternatives=false&geometries=geojson&steps=false";
        
        // Realizar la petición a la API de Mapbox
        $response = $client->get($url);

        // Decodificar la respuesta JSON
        $data = json_decode($response->getBody(), true);

        // Verificamos si la respuesta fue exitosa
        if (isset($data['routes'][0]['duration'])) {
            // La duración está en segundos, la convertimos a minutos
            $duration = $data['routes'][0]['duration'] / 60;
            return round($duration, 2);  // Devolver el tiempo estimado en minutos
        }

        return null;  // En caso de que no se pueda obtener el tiempo estimado
    }

    // Método para obtener el listado de pedidos y calcular el tiempo estimado
    public function obtenerPedidosConTiempoEstimado($idMotorizado)
    {
        // Obtener la ubicación del motorizado
        $motorizadoLocation = Location::where('motorizado_id', $idMotorizado)
            ->latest()
            ->first();

        // Obtener los pedidos con el id_local correspondiente
        $pedidos = Pedido::whereNotNull('id_local')->get();

        foreach ($pedidos as $pedido) {
            // Obtener el establecimiento asociado al pedido
            $local = Establecimiento::find($pedido->id_local);

            if ($motorizadoLocation && $local) {
                // Calcular la distancia y tiempo estimado entre el motorizado y el local
                $tiempoEstimado = $this->obtenerTiempoEstimado(
                    $motorizadoLocation->latitud,
                    $motorizadoLocation->longitud,
                    $local->latitud,
                    $local->longitud
                );

                // Si el tiempo estimado es válido, agregarlo al pedido
                if ($tiempoEstimado !== null) {
                    $pedido->tiempo_estimado = $tiempoEstimado;
                    $pedido->save();
                }
            }
        }

        return $pedidos;
    }
}
