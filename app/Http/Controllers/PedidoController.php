<?php

namespace App\Http\Controllers;

use App\Mail\PedidoEntregadoMail;
use App\Models\Adicional;
use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\DescuentoCliente;
use App\Models\Establecimiento;
use App\Models\Menu;
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
        $pedido = Pedido::create($request->only([
            'id_local',
            'id_cliente',
            'latitud',
            'longitud',
            'nota',
            'id_tipo_pago',
            'tipo_comprobante',
            'documento',
            'precio_delivery',
            'descuento',
            'subtotal',
            'codigo',
        ]));

        $totalPedido = 0;

        foreach ($request->items as $item) {
            $precio = preg_replace('/[^\d.]/', '', $item['price']);
            $totalPedido += $precio * ($item['quantity'] ?? 1);

            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'id_producto' => $item['id'],
                'nombre' => $item['name'],
                'cantidad' => $item['quantity'] ?? 1,
                'precio' => $precio,
                'tipo' => 'item',
            ]);
        }

        foreach ($request->adicionales as $adicional) {
            $precio = preg_replace('/[^\d.]/', '', $adicional['price']);
            $totalPedido += $precio;

            PedidoDetalle::create([
                'pedido_id' => $pedido->id,
                'id_producto' => $adicional['id'],
                'nombre' => $adicional['name'],
                'cantidad' => 1,
                'precio' => $precio,
                'tipo' => 'adicional',
            ]);
        }

        $requiereConfirmacion = $totalPedido > 100;

        $pedido->requiere_confirmacion_local = $requiereConfirmacion;

        //descontar uso del cupon
        $descuento = DescuentoCliente::where('id_cliente', $request->id_cliente)
            ->where('codigo', $request->codigo)
            ->where('estado', 1)
            ->first();
            
        if ($descuento) {
            if (($descuento->usos_disponibles - 1) == 0) {
                $descuento->usos_disponibles = 0;
            } else {
                $descuento->usos_disponibles = $descuento->usos_disponibles - 1;
            }
            $descuento->cantidad_usos = 1;
            $descuento->save();
        }

        PedidoTracking::create(['pedido_id' => $pedido->id, 'estado' => 1]);
        $pedido->save();

        $comercio = BusinessRegistration::find($request->id_local);

        if ($comercio->token_fmc) {
            $this->firebaseService->sendNotification(
                $comercio->token_fmc,
                '🛒 Nuevo Pedido de ' . Cliente::where('id', $request->id_cliente)->first()->nombre,
                'El pedido #' . $pedido->id . ' ya está disponible para procesar.'
            );
        }

        return response()->json([
            'status' => 'success',
            'pedido_id' => $pedido->id,
            'requiere_confirmacion' => $requiereConfirmacion,
            'precio_delivery' => $precio
        ]);
    }


    public function calcularPrecioDelivery($idLocal, $idCliente)
    {
        $local = Establecimiento::where('business_registration_id', $idLocal)->first();

        $clienteDireccion = ClienteDireccion::where('id_cliente', $idCliente)->first();
        $coordenadas = json_decode($clienteDireccion->coordenadas);
        $distancia = $this->pedidoService->obtenerDistancia(
            $local->latitud,
            $local->longitud,
            $coordenadas->coordinates[1],
            $coordenadas->coordinates[0]
        );

        $precio_delivery = $distancia ? $this->pedidoService->calcularPrecioPorDistancia($distancia) : 0;

        // Formatear con 2 decimales
        return response()->json(number_format((float)$precio_delivery, 2, '.', ''));
    }

    public function sendMotorizadosCerca()
    {
        $motorizadosToken = $this->pedidoService->obtenerPedidosCercanos();
        foreach ($motorizadosToken as $token) {
            if ($token) {
                $this->firebaseService->sendNotification($token, '🛵 Nuevo Pedido Disponible', '📍 Un nuevo pedido está disponible. ¡No lo dejes pasar!');
            }
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

        if ($request->estado == 2) {
            $this->sendMotorizadosCerca();
        }

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
            if ($pedidoTracking) {
                $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
                $logo = PerfilNegocio::where('business_registration_id', $pedido->id_local)->first();
                $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                $total = $pedidoDetalles->sum('precio');
                $existeCalificacion = Rating::where('id_pedido', $pedido->id)->first() ? true : false;
                if ($pedidoTracking->estado !== 8) {
                    $existeCalificacion = true;
                }
                $data[] = [
                    'id' => $pedido->id,
                    'estado' => estadoPedido($pedidoTracking->estado),
                    'estado_numero' => (int) $pedidoTracking->estado,
                    'fecha_entrega' => Carbon::parse($pedidoTracking->created_at)->format('d/m/Y'),
                    'hora_entrega' => Carbon::parse($pedidoTracking->created_at)->format('H:i'),
                    'local' => $local->nombre_establecimiento,
                    'logo' => $logo->ruta_logo ? env('APP_URL') . '/' . $logo->ruta_logo : 'https://magusemail.com/truelove-back/public/default_avatar.png',
                    'total' => $total,
                    'cantidad' => count($pedidoDetalles),
                    'direccion' => ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first()->direccion,
                    'created_at' => $pedido->created_at,
                    'requiere_confirmacion_local' => $pedido->requiere_confirmacion_local == 1 ? true : false,
                    'existeCalificacion' => $existeCalificacion,
                ];
            }
        }
        usort($data, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });
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
            if ($pedidoTracking && $pedidoTracking->estado == 8) {
                $rating[] = Rating::where('id_pedido', $pedido->id)->first()->motorcycle_rating ?? 0;
            }
        }

        $promedio = count($rating) ? number_format(array_sum($rating) / count($rating), 1, '.', '') : '0.0';

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
            // if ($pedidoTracking->estado == 8) {
            $rating = Rating::where('id_pedido', $pedido->id)->first();
            if ($rating) {
                $cliente = Cliente::where('id', $pedido->id_cliente)->first();
                $coment[] = [
                    'id' => $pedido->id,
                    'comentario' => $rating->motorcycle_comment,
                    'rating' => number_format($rating->motorcycle_rating, 1, '.', ''),
                    'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
                ];
            }
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

    public function repetirOrden($idPedido)
    {
        $pedido = Pedido::find($idPedido);
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // 1) IDs de producto que pide la orden (solo tipo 'item')
        $detalleIds = PedidoDetalle::where('pedido_id', $idPedido)
            ->where('tipo', 'item')
            ->pluck('id_producto')   // colección de ints
            ->toArray();

        // 2) IDs de menú activos para este local
        $activeMenuIds = Menu::where('empresa_id', $pedido->id_local)
            ->where('status', 'active')
            ->pluck('id')            // colección de ints
            ->toArray();

        // 3) ¿Hay algún ID pedido que NO esté en activeMenuIds?
        $faltantes = array_diff($detalleIds, $activeMenuIds);
        if (!empty($faltantes)) {
            // Al menos uno de los productos ya no está activo
            return response()->json([]);
        }

        // 4) Como todo coincide, recuperamos los datos de los productos
        $productos = Menu::whereIn('id', $detalleIds)->get()->keyBy('id');

        // 5) Construimos el arreglo de items con cantidad
        $items = [];
        foreach (PedidoDetalle::where('pedido_id', $idPedido)->where('tipo', 'item')->get() as $detalle) {
            $menu = $productos[$detalle->id_producto];
            $items[] = [
                'id'               => $menu->id,
                'name'             => $menu->titulo,
                'category'         => $menu->descripcion,
                'image'            => $menu->foto,
                'price'            => $menu->precio,
                'discountedPrice'  => 0,
                'quantity'         => $detalle->cantidad,
            ];
        }

        return response()->json($items);
    }

    public function verificarConfirmacion($id)
    {
        $pedido = Pedido::findOrFail($id);
        return response()->json([
            'requiere_confirmacion' => $pedido->requiere_confirmacion_local
        ]);
    }

    public function updateVerificarConfirmacion($id)
    {
        $pedido = Pedido::findOrFail($id);
        $pedido->requiere_confirmacion_local = 0;
        $pedido->save();
        return response()->json(true);
    }
}
