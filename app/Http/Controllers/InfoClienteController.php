<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InfoClienteController extends Controller
{
    /**
     * Obtiene todos los clientes (sin paginación, como en SocioController)
     */
    public function all()
    {
        try {
            $clientes = Cliente::orderBy('created_at', 'desc')->get();
            return response()->json($clientes);
        } catch (\Exception $e) {
            Log::error('Error al obtener clientes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la lista de clientes'
            ], 500);
        }
    }

    /**
     * Obtiene los detalles completos de un cliente
     */
    public function getDetails($id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            // Obtener las direcciones del cliente (usar id_cliente)
            $direcciones = ClienteDireccion::where('id_cliente', $id)->get();

            // Obtener estadísticas de pedidos (usar id_cliente)
            $totalPedidos = Pedido::where('id_cliente', $id)->count();
            
            // Contar pedidos por estado usando la relación con trackings
            // Estados: 0 = cancelado, 8 = entregado
            $pedidosCompletados = Pedido::where('id_cliente', $id)
                ->whereHas('trackings', function($query) {
                    $query->where('estado', 8); // 8 = entregado
                })
                ->count();

            $pedidosCancelados = Pedido::where('id_cliente', $id)
                ->whereHas('trackings', function($query) {
                    $query->where('estado', 0); // 0 = cancelado
                })
                ->count();

            // Obtener el último pedido con su tracking más reciente
            $ultimoPedido = Pedido::where('id_cliente', $id)
                ->with(['trackings' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }])
                ->orderBy('created_at', 'desc')
                ->first();

            // Calcular el total del pedido (subtotal - descuento + delivery)
            $totalUltimoPedido = null;
            $estadoUltimoPedido = null;
            if ($ultimoPedido) {
                $totalUltimoPedido = ($ultimoPedido->subtotal ?? 0) 
                    - ($ultimoPedido->descuento ?? 0) 
                    + ($ultimoPedido->precio_delivery ?? 0);
                
                // Obtener el estado del último tracking
                if ($ultimoPedido->trackings && $ultimoPedido->trackings->isNotEmpty()) {
                    $estadoUltimoPedido = $ultimoPedido->trackings->first()->estado;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $cliente->id,
                    'personal' => [
                        'nombre' => $cliente->nombre,
                        'apellido' => $cliente->apellido,
                        'nombre_completo' => $cliente->nombre . ' ' . $cliente->apellido,
                        'email' => $cliente->email,
                        'documento' => $cliente->documento,
                        'celular' => $cliente->celular,
                        'fecha_nacimiento' => $cliente->fecha_nacimiento,
                        'genero' => $cliente->genero,
                        'nacionalidad' => $cliente->nacionalidad,
                        'foto_perfil' => $cliente->foto_perfil,
                        'dni_photo' => $cliente->dni_photo,
                        'selfie_photo' => $cliente->selfie_photo,
                        'created_at' => $cliente->created_at,
                        'updated_at' => $cliente->updated_at,
                    ],
                    'direcciones' => $direcciones->map(function ($direccion) {
                        // Parsear coordenadas si existen (formato: "lat,lng")
                        $latitud = null;
                        $longitud = null;
                        if ($direccion->coordenadas) {
                            $coords = explode(',', $direccion->coordenadas);
                            if (count($coords) === 2) {
                                $latitud = trim($coords[0]);
                                $longitud = trim($coords[1]);
                            }
                        }
                        
                        return [
                            'id' => $direccion->id,
                            'direccion' => $direccion->direccion,
                            'referencia' => $direccion->referencia,
                            'alias' => $direccion->alias,
                            'departamento' => $direccion->departamento,
                            'coordenadas' => $direccion->coordenadas,
                            'latitud' => $latitud,
                            'longitud' => $longitud,
                            'created_at' => $direccion->created_at,
                        ];
                    }),
                    'estadisticas' => [
                        'total_pedidos' => $totalPedidos,
                        'pedidos_completados' => $pedidosCompletados,
                        'pedidos_cancelados' => $pedidosCancelados,
                        'ultimo_pedido' => $ultimoPedido ? [
                            'id' => $ultimoPedido->id,
                            'fecha' => $ultimoPedido->created_at,
                            'estado' => $estadoUltimoPedido,
                            'total' => $totalUltimoPedido,
                        ] : null,
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del cliente: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los detalles del cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un cliente y todos sus datos relacionados
     */
    public function delete($id)
    {
        DB::beginTransaction();
        try {
            // Buscar el cliente
            $cliente = Cliente::findOrFail($id);

            // Verificar si tiene pedidos activos (no entregados ni cancelados)
            // Estados: 0 = cancelado, 8 = entregado
            // Contar pedidos que NO tienen estado 0 (cancelado) o 8 (entregado) en su último tracking
            $pedidosActivos = Pedido::where('id_cliente', $id)
                ->whereDoesntHave('trackings', function($query) {
                    $query->whereIn('estado', [0, 8]); // 0 = cancelado, 8 = entregado
                })
                ->count();

            if ($pedidosActivos > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No se puede eliminar el cliente porque tiene pedidos activos',
                    'pedidos_activos' => $pedidosActivos
                ], 400);
            }

            // Eliminar las direcciones del cliente (usar id_cliente)
            ClienteDireccion::where('id_cliente', $id)->delete();

            // Nota: Los pedidos NO se eliminan, solo se mantienen como histórico
            // Si quieres eliminarlos también, descomenta las siguientes líneas:
            // $pedidos = Pedido::where('id_cliente', $id)->get();
            // foreach ($pedidos as $pedido) {
            //     $pedido->trackings()->delete();
            //     $pedido->detalles()->delete();
            //     $pedido->delete();
            // }

            // Eliminar el cliente
            $cliente->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar cliente: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el cliente: ' . $e->getMessage()
            ], 500);
        }
    }
}
