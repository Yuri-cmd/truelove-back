<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\Location;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\RepartoRegistro;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class BikerController extends Controller
{
    protected $apiKey = '***MAPBOX_TOKEN_REMOVED***';

    public function login(Request $request)
    {
        // Buscar el reparto_registro
        $reparto = RepartoRegistro::where('email', $request->email)
            ->where('estado', 1) // Estado debe ser 1
            ->where('aprobado', 1) // Aprobación debe ser 1
            ->first();

        if (!$reparto) {
            return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado o no aprobado'], 404);
        }

        // Buscar el usuario relacionado
        $user = User::find($reparto->user_id);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales incorrectas'], 401);
        }

        // Si todo está bien, devolver el usuario y token
        // Asumiendo que usas Sanctum o Passport para autenticación con tokens
        return response()->json([
            'status' => 'success',
            'message' => 'Inicio de sesión exitoso',
            'user' => $user,
            'token' => $user->createToken('your-app-name')->plainTextToken,
        ]);
    }

    public function getPedidos($idMotorizado)
    {
        $pedidos = $this->obtenerPedidosConTiempoEstimado($idMotorizado);
        return response()->json($pedidos);
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
                    // Si está dentro del radio, calcular el tiempo estimado
                    $tiempoEstimado = $this->obtenerTiempoEstimadoTresPuntos(
                        $motorizadoLocation->latitude,
                        $motorizadoLocation->longitude,
                        $local->latitud,
                        $local->longitud,
                        $pedido->latitud,
                        $pedido->longitud,
                    );

                    // Si el tiempo estimado es válido, agregarlo al pedido
                    if ($tiempoEstimado !== null) {
                        $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                        $names = array_map(function ($item) {
                            return $item['nombre'];
                        }, $pedidoDetalles->toArray());
                        $namesString = implode(', ', $names);
                        $pedido->tiempo_estimado = $tiempoEstimado;
                        $pedido->detalle = $namesString;
                    }
                }
            }
        }

        return $pedidos;
    }
}
