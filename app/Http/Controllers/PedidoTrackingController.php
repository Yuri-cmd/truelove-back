<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PedidoTracking;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class PedidoTrackingController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }
    public function obtenerEstado($id)
    {
        $pedido = PedidoTracking::where('pedido_id', $id)->latest()->first();
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }
        $tiempo = Pedido::find($id)->tiempo;
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();

        return response()->json([
            'id' => $id,
            'estado' => $pedido->estado,
            'tiempo' => $tiempo ?? 0,
            'tieneMotorizado' => Pedido::find($id)->id_motorizado ? true : false,
            'direccionLocal' => $local ? $local->direccion_completa : null,
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

        $local_fmc = BusinessRegistration::find($pedido->id_local)->token_fmc;
        $cliente_fmc = Cliente::find($pedido->id_cliente)->token_fmc;

        $estadoTitulo = estadoPedido($request->estado);
        $mensajeLocal = mensajeNotificacionPedido($request->estado, $pedido->id, 'local');
        $mensajeCliente = mensajeNotificacionPedido($request->estado, $pedido->id, 'cliente');

        if ($local_fmc) {
            $this->firebaseService->sendNotification(
                $local_fmc,
                $estadoTitulo,
                $mensajeLocal
            );
        }

        if ($cliente_fmc) {
            $this->firebaseService->sendNotification(
                $cliente_fmc,
                $estadoTitulo,
                $mensajeCliente
            );
        }

        return response()->json(['status' => 'success']);
    }
}
