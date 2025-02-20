<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\PerfilNegocio;
use App\Services\FirebaseService;
use App\Services\PedidoService;

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
            'estado' => 3
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
        $perfil = PerfilNegocio::find($pedido->id_local);
        $local = Establecimiento::where('business_registration_id', $perfil->business_registration_id)->first();
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
}
