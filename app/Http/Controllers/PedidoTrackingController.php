<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Negocio;
use App\Models\Pedido;
use App\Models\PedidoTracking;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PedidoTrackingController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    public function obtenerEstado($id)
    {
        $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest()->first();
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
        PedidoTracking::create([
            'pedido_id' => $pedido->id,
            'estado' => $request->estado
        ]);

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
                $mensajeLocal
            );
        }

        if ($cliente_fmc && !empty($cliente_fmc)) {
            $this->firebaseService->sendNotification(
                $cliente_fmc,
                $estadoTitulo,
                $mensajeCliente
            );
        }

        return response()->json(['status' => 'success']);
    }
}
