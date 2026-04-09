<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\PedidoTracking;
use App\Models\RepartoRegistro;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PedidoTrackingController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    public function obtenerEstado($id)
    {
        $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest('id')->first();
        if (!$ultimoTracking) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $pedido = Pedido::find($id);
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $negocio = Negocio::where('business_registration_id', $pedido->id_local)->first();

        // Calcular minutos transcurridos de forma más robusta usando timestamps
        $now = Carbon::now();
        $createdAt = $pedido->created_at ? Carbon::parse($pedido->created_at) : $now;
        $minutosTranscurridos = (int) floor($createdAt->diffInSeconds($now) / 60);
        // Calculo del total
        $totalItems = DB::table('pedido_detalles')
            ->where('pedido_id', $pedido->id)
            ->sum(DB::raw('precio * cantidad'));
        $total = $totalItems;
        $precioDelivery = 0;
        if ((string)$pedido->tipo_pedido === '0') {
            $precioDelivery = ($pedido->precio_delivery ?? 0);
            $total += $precioDelivery;
        }
        $descuento = ($pedido->descuento ?? 0);
        $total -= $descuento;

        return response()->json([
            'id' => $id,
            'estado' => $ultimoTracking->estado,
            'tiempo' => $pedido->tiempo ?? 0,
            'minutos_transcurridos' => $minutosTranscurridos,
            'tieneMotorizado' => $pedido->id_motorizado ? true : false,
            'direccionLocal' => $local ? $local->direccion_completa : null,
            'lat' => $local ? $local->latitud : null,
            'lon' => $local ? $local->longitud : null,
            'telefono' => $local ? $negocio->telefono : null,
            'subtotal' => round($totalItems, 2),
            'precio_delivery' => round($precioDelivery, 2),
            'descuento' => round($descuento, 2),
            'total' => round($total, 2),
            'created_at' => $createdAt->toDateTimeString(),
            'server_time' => $now->toDateTimeString(),
        ]);
    }

    public function updateEstado(Request $request)
    {
        // Buscar el pedido por su ID
        $pedido = Pedido::find($request->id);
        // Verificar si el pedido existe
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }
        // Registrar el tracking del pedido
        $tracking = new PedidoTracking([
            'pedido_id' => $pedido->id,
            'estado' => $request->estado
        ]);
        $tracking->setTraceability($request);
        $tracking->save();

        $local = BusinessRegistration::find($pedido->id_local);
        $cliente = Cliente::find($pedido->id_cliente);

        $local_fmc = $local ? $local->token_fmc : null;
        $cliente_fmc = $cliente ? $cliente->token_fmc : null;

        $estadoTitulo = estadoPedido($request->estado);
        $mensajeLocal = mensajeNotificacionPedido($request->estado, $pedido->id, 'local');
        $mensajeCliente = mensajeNotificacionPedido($request->estado, $pedido->id, 'cliente');

        if ($local_fmc && !empty($local_fmc)) {
            $this->firebaseService->sendNotification(
                $local_fmc,
                $estadoTitulo,
                $mensajeLocal,
                [],
                'socio',
                $pedido->id_local,
                'socio'
            );
        }

        if ($cliente_fmc && !empty($cliente_fmc)) {
            $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado) : null;
            $telMotorizado = $motorizado ? $motorizado->celular : null;
            
            $this->firebaseService->sendNotification(
                $cliente_fmc,
                $estadoTitulo,
                $mensajeCliente,
                [
                    'type' => 'order_status_update',
                    'order_id' => (string)$pedido->id,
                    'progress' => (string)progresoPedido($request->estado),
                    'tiempo' => (string)($pedido->tiempo ?? 0),
                    'tel_motorizado' => (string)($telMotorizado ?? ''),
                ],
                'cliente',
                $pedido->id_cliente,
                'cliente'
            );
        }

        return response()->json(['status' => 'success']);
    }
}
