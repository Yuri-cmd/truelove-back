<?php

namespace App\Http\Controllers;

use App\Mail\PedidoEntregadoMail;
use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\PerfilNegocio;
use App\Models\Rating;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class PedidoController extends Controller
{

    private $firebaseService;
    private $pedidoService;

    public function __construct(FirebaseService $firebaseService, PedidoService $pedidoService)
    {
        $this->firebaseService = $firebaseService;
        $this->pedidoService = $pedidoService;
    }

    public function store(Request $request)
    {
        $pedido = Pedido::create($request->only(['id_local', 'id_cliente', 'latitud', 'longitud']));

        foreach ($request->items as $item) {
            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'nombre' => $item['name'],
                'cantidad' => $item['quantity'] ?? 1,
                'precio' => preg_replace('/[^\d.]/', '', $item['price']),
                'tipo' => 'item',
            ]);
        }

        foreach ($request->adicionales as $adicional) {
            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'nombre' => $adicional['name'],
                'cantidad' => 1,
                'precio' => preg_replace('/[^\d.]/', '', $adicional['price']),
                'tipo' => 'adicional',
            ]);
        }

        PedidoTracking::create(['pedido_id' => $pedido->id, 'estado' => 1]);

        $this->sendMotorizadosCerca();

        return response()->json(['status' => 'success', 'pedido_id' => $pedido->id]);
    }

    public function sendMotorizadosCerca()
    {
        $motorizadosToken = $this->pedidoService->obtenerPedidosCercanos();
        foreach ($motorizadosToken as $token) {
            $this->firebaseService->sendNotification($token, '🛵 Nuevo Pedido Disponible', '📍 Un nuevo pedido está disponible. ¡No lo dejes pasar!');
        }
    }

    public function iniciarViaje(Request $request)
    {
        // Buscar el pedido por su ID
        $pedido = Pedido::find($request->id);

        // Verificar si el pedido existe
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        // Asignar el id_motorizado y guardar
        $pedido->id_motorizado = $request->id_motorizado;
        $pedido->save();

        // Registrar el tracking del pedido
        PedidoTracking::create([
            'pedido_id' => $pedido->id,
            'estado' => 4
        ]);

        return response()->json(['status' => 'success']);
    }

    public function updateEstadoPedido(Request $request, $id)
    {
        // Verificar si el pedido existe
        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }
        if ($request->tiempo) {
            $pedido->tiempo = $request->tiempo;
            $pedido->save();
        }
        // Crear un nuevo tracking para el pedido
        $tracking = new PedidoTracking();
        $tracking->pedido_id = $id;
        $tracking->estado = $request->estado;
        $tracking->save();

        // Retornar respuesta exitosa
        return response()->json(['message' => 'Estado actualizado correctamente'], 200);
    }

    public function getLocalYcustomerPosition($idPedido)
    {
        // Verificar si el pedido existe
        $pedido = Pedido::find($idPedido);
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $cliente = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
        $coordenadasCliente = json_decode($cliente->coordenadas);
        $resp = [
            'locallat' => $local->latitud,
            'locallon' => $local->longitud,
            'custlat' => $coordenadasCliente->coordinates[0],
            'custlon' => $coordenadasCliente->coordinates[1],
            'tiempo' => $pedido->tiempo ?? 0,
        ];
        // Retornar respuesta exitosa
        return response()->json($resp);
    }

    public function getPedidosCliente($idCliente)
    {
        $data = [];
        $pedidos = Pedido::where('id_cliente', $idCliente)->get();
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
            $logo = PerfilNegocio::where('business_registration_id', $pedido->id_local)->first()->ruta_logo;
            $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
            $total = $pedidoDetalles->sum('precio');
            $data[] = [
                'id' => $pedido->id,
                'estado' => estadoPedido($pedidoTracking->estado),
                'fecha_entrega' => $pedidoTracking->created_at,
                'local' => $local->nombre_establecimiento,
                'logo' => env('APP_URL') . '/' . $logo,
                'total' => $total,
                'cantidad' => count($pedidoDetalles),
                'direccion' => ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first()->direccion,
                'created_at' => $pedido->created_at,
            ];
        }
        return response()->json($data);
    }

    public function getMotorizado($idPedido)
    {
        $pedido = Pedido::find($idPedido);
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }
        $motorizado = RepartoRegistro::find($pedido->id_motorizado);
        if (!$motorizado) {
            return response()->json(['error' => 'Motorizado no encontrado'], 404);
        }

        $pedidos = Pedido::where('id_motorizado', $motorizado->id)->get();
        $pedidoCount = $pedidos->count();

        // obtener rating 
        $rating = [];
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if ($pedidoTracking->estado == 7) {
                $rating[] = Rating::where('id_pedido', $pedido->id)->first()->motorcycle_rating;
            }
        }

        $promedio = number_format(array_sum($rating) / count($rating), 1, '.', '');

        return response()->json([
            'id' => $motorizado->id,
            'nombre' => $motorizado->nombres . ' ' . $motorizado->apellidos,
            'celular' => $motorizado->celular,
            'foto' => env('APP_URL') . '/' . $motorizado->ruta_foto,
            'pedidoCount' => $pedidoCount,
            'rating' => $promedio,
        ]);
    }

    public function getMotorizadoInfo($idMotorizado)
    {
        $pedidos = Pedido::where('id_motorizado', $idMotorizado)->get();
        $coment = [];
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            // if ($pedidoTracking->estado == 6) {
            $rating = Rating::where('id_pedido', $pedido->id)->first();
            $cliente = Cliente::where('id', $pedido->id_cliente)->first();
            $coment[] = [
                'id' => $pedido->id,
                'comentario' => $rating->motorcycle_comment,
                'rating' => number_format($rating->motorcycle_rating, 1, '.', ''),
                'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
            ];
            // }
        }
        return response()->json($coment);
    }

    public function getRestaurantInfo($idLocal)
    {
        $pedidos = Pedido::where('id_local', $idLocal)->get();
        $coment = [];
        $ratings = [];
        $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]; // Inicializamos el contador de ratings

        foreach ($pedidos as $pedido) {
            $rating = Rating::where('id_pedido', $pedido->id)->first();
            $cliente = Cliente::where('id', $pedido->id_cliente)->first();

            if ($rating) {
                $roundedRating = round($rating->restaurant_rating); // Redondeamos el rating a entero
                if (isset($ratingCounts[$roundedRating])) {
                    $ratingCounts[$roundedRating]++; // Aumentamos el contador correspondiente
                }

                $ratings[] = $rating->restaurant_rating;
                $coment[] = [
                    'id' => $pedido->id,
                    'comentario' => $rating->motorcycle_comment,
                    'rating' => number_format($rating->restaurant_rating, 1, '.', ''),
                    'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
                ];
            }
        }

        $pedidoCount = $pedidos->count();
        $promedio = count($ratings) > 0 ? number_format(array_sum($ratings) / count($ratings), 1, '.', '') : "0.0";

        $data = [
            'id' => $idLocal,
            'comentarios' => $coment,
            'pedidoCount' => $pedidoCount,
            'rating' => $promedio,
            'ratingCounts' => $ratingCounts // Agregamos la distribución de ratings
        ];

        return response()->json($data);
    }

    public function enviarCorreoPedidoEntregado(Request $request)
    {
        $pedido = Pedido::find($request->id_pedido);

        if (!$pedido) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }
        $pedido->total = PedidoDetalle::where('pedido_id', $pedido->id)->sum('precio');
        $pedido->fecha_entrega = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first()->created_at;
        $pedido->cliente = Cliente::find($pedido->id_cliente)->only(['nombre', 'apellido', 'email']);
        $pedido->motorizado = RepartoRegistro::find($pedido->id_motorizado)->only(['nombres', 'apellidos', 'celular']);

        Mail::to($pedido->cliente['email'])->send(new PedidoEntregadoMail($pedido));

        return response()->json(['message' => 'Correo enviado con éxito']);
    }

    public function getPedido($id)
    {
        $pedido = Pedido::with(['trackings' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $cliente = Cliente::find($pedido->id_cliente);
        $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
        $motorizado = RepartoRegistro::find($pedido->id_motorizado);

        $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
        $detalleNombres = $pedidoDetalles->pluck('nombre')->implode(', ');

        $ultimoTracking = $pedido->trackings->first();
        $estadoTracking = $ultimoTracking ? estadoPedido($ultimoTracking->estado) : 'Sin seguimiento';

        $pedido->motorizado = $motorizado ? trim(($motorizado->nombres ?? '') . ' ' . ($motorizado->apellidos ?? '')) : '';
        $pedido->celular_motorizado = $motorizado->celular ?? '';

        $pedido->detalle = $detalleNombres;
        $pedido->detalleArray = $pedidoDetalles;
        $pedido->ultimo_estado_tracking = $ultimoTracking->estado ?? 'Sin seguimiento';
        $pedido->estado = $estadoTracking;

        $pedido->local = $local->nombre_establecimiento ?? '';
        $pedido->direccion_local = $local->direccion_completa ?? '';
        $pedido->direccion_entrega = $clienteDireccion->direccion ?? '';
        $pedido->cliente = $cliente ? "{$cliente->nombre} {$cliente->apellido}" : '';
        $pedido->celular = $cliente->celular ?? '';
        $pedido->lat_local = $local->latitud ?? '';
        $pedido->lon_local = $local->longitud ?? '';
        $pedido->tiempo = $pedido->tiempo ?? 0;

        return response()->json($pedido);
    }

    public function mandarAlertaDeAuxilio(Request $request)
    {
        $pedido = Pedido::find($request->id_pedido);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $motorizado = RepartoRegistro::find($pedido->id_motorizado)->only(['nombres', 'apellidos', 'celular']);
        $nombre = $motorizado['nombres'] . ' ' . $motorizado['apellidos'];
        $motorizados = RepartoRegistro::where('estado', 1)->where('aprobado', 1)->get();
        $motorizadosToken = [];
        foreach ($motorizados as $motorizado) {
            $motorizadosToken[] = $motorizado->token_fmc;
        }

        foreach ($motorizadosToken as $token) {
            $this->firebaseService->sendNotification($token, '🛵 Alerta!', "📍 El motorizado {$nombre} todavia no finaliza su viaje");
        }
        return response()->json(['status' => 'success']);
    }
}
