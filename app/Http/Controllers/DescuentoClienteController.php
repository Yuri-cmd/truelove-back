<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\DescuentoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\FirebaseService;
use Carbon\Carbon;

class DescuentoClienteController extends Controller
{
    private $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Obtener todos los descuentos
     */
    public function index()
    {
        $descuentos = DescuentoCliente::with('cliente')->get();
        return response()->json($descuentos);
    }

    /**
     * Crear un nuevo descuento
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_cliente' => 'required|exists:clientes,id',
            'tipo_descuento' => 'required|in:porcentaje,monto_fijo,delivery_gratis',
            'valor' => 'required_unless:tipo_descuento,delivery_gratis|numeric|min:0',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'usos_disponibles' => 'nullable|integer|min:1',
            'descripcion' => 'nullable|string|max:255'
        ]);

        // Generar código único
        $codigo = strtoupper(Str::random(8));

        $descuento = DescuentoCliente::create([
            'id_cliente' => $request->id_cliente,
            'tipo_descuento' => $request->tipo_descuento,
            'valor' => $request->tipo_descuento == 'delivery_gratis' ? 0 : $request->valor,
            'codigo' => $codigo,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'estado' => 1, // Activo por defecto
            'cantidad_usos' => 0, // Inicialmente no se ha usado
            'usos_disponibles' => $request->usos_disponibles,
            'descripcion' => $request->descripcion
        ]);

        if ($descuento) {
            $cliente = Cliente::find($request->id_cliente);
            if ($cliente->token_fmc) {
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    '¡Tienes un nuevo descuento disponible! 🎉',
                    "¡Hola {$cliente->nombre}! Hemos agregado un nuevo descuento a tu cuenta. Utiliza el código: {$codigo} y disfruta de tus beneficios exclusivos. ¡Aprovéchalo antes de que expire!"
                );
            }
        }

        return response()->json([
            'message' => 'Descuento creado con éxito',
            'descuento' => $descuento
        ], 201);
    }

    /**
     * Obtener un descuento específico
     */
    public function show($id)
    {
        $descuento = DescuentoCliente::with('cliente')->findOrFail($id);
        return response()->json($descuento);
    }

    /**
     * Actualizar un descuento existente
     */
    public function update(Request $request, $id)
    {
        $descuento = DescuentoCliente::findOrFail($id);

        $request->validate([
            'tipo_descuento' => 'sometimes|required|in:porcentaje,monto_fijo,delivery_gratis',
            'valor' => 'required_unless:tipo_descuento,delivery_gratis|numeric|min:0',
            'fecha_inicio' => 'sometimes|required|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
            'estado' => 'sometimes|boolean',
            'usos_disponibles' => 'nullable|integer|min:1',
            'descripcion' => 'nullable|string|max:255'
        ]);

        // Si el tipo de descuento es delivery_gratis, establecer valor a 0
        if ($request->has('tipo_descuento') && $request->tipo_descuento == 'delivery_gratis') {
            $request->merge(['valor' => 0]);
        }

        $descuento->update($request->all());

        return response()->json([
            'message' => 'Descuento actualizado con éxito',
            'descuento' => $descuento
        ]);
    }

    /**
     * Eliminar un descuento
     */
    public function destroy($id)
    {
        $descuento = DescuentoCliente::findOrFail($id);
        $descuento->delete();

        return response()->json([
            'message' => 'Descuento eliminado con éxito'
        ]);
    }

    /**
     * Verificar si un cliente tiene descuentos activos
     */
    public function clienteDescuentos($idCliente)
    {
        $descuentos = DescuentoCliente::where('id_cliente', $idCliente)
            ->where('estado', 1)
            ->where(function ($query) {
                $query->where('fecha_fin', '>=', now()->toDateString())
                    ->orWhereNull('fecha_fin');
            })
            ->where(function ($query) {
                $query->whereNull('usos_disponibles')
                    ->orWhere('cantidad_usos', '<', DB::raw('usos_disponibles'));
            })
            ->get();

        return response()->json($descuentos);
    }

    /**
     * Verificar y aplicar un descuento
     */
    public function aplicarDescuento(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string',
            'monto_total' => 'required|numeric|min:0',
            'costo_delivery' => 'required|numeric|min:0'
        ]);

        $descuento = DescuentoCliente::where('codigo', $request->codigo)
            ->where('estado', 1)
            ->where(function ($query) {
                $query->where('fecha_fin', '>=', now()->toDateString())
                    ->orWhereNull('fecha_fin');
            })
            ->first();

        if (!$descuento) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código de descuento inválido o expirado'
            ], 404);
        }

        // Verificar si aún tiene usos disponibles
        if ($descuento->usos_disponibles !== null && $descuento->cantidad_usos >= $descuento->usos_disponibles) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este código de descuento ya ha alcanzado el límite de usos'
            ], 400);
        }

        // Aplicar el descuento según su tipo
        $resultado = [];
        $descuentoAplicado = 0;

        switch ($descuento->tipo_descuento) {
            case 'porcentaje':
                $descuentoAplicado = $request->monto_total * ($descuento->valor / 100);
                $resultado = [
                    'monto_original' => $request->monto_total,
                    'monto_con_descuento' => $request->monto_total - $descuentoAplicado,
                    'costo_delivery' => $request->costo_delivery,
                    'descuento_aplicado' => $descuentoAplicado,
                    'tipo_descuento' => 'porcentaje',
                    'valor' => $descuento->valor . '%'
                ];
                break;

            case 'monto_fijo':
                $descuentoAplicado = min($request->monto_total, $descuento->valor);
                $resultado = [
                    'monto_original' => $request->monto_total,
                    'monto_con_descuento' => $request->monto_total - $descuentoAplicado,
                    'costo_delivery' => $request->costo_delivery,
                    'descuento_aplicado' => $descuentoAplicado,
                    'tipo_descuento' => 'monto_fijo',
                    'valor' => $descuento->valor
                ];
                break;

            case 'delivery_gratis':
                $descuentoAplicado = $request->costo_delivery;
                $resultado = [
                    'monto_original' => $request->monto_total,
                    'monto_con_descuento' => $request->monto_total,
                    'costo_delivery' => 0,
                    'descuento_aplicado' => $descuentoAplicado,
                    'tipo_descuento' => 'delivery_gratis',
                    'valor' => 'Delivery Gratis'
                ];
                break;
        }

        // Incrementar la cantidad de usos
        $descuento->cantidad_usos += 1;
        $descuento->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Descuento aplicado correctamente',
            'descuento' => $descuento,
            'resultado' => $resultado,
            'total_final' => $resultado['monto_con_descuento'] + $resultado['costo_delivery']
        ]);
    }

    /**
     * Obtener los clientes con más pedidos completados
     */
    public function getTopClientsWithCompletedOrders()
    {
        try {
            // Obtener los clientes con más pedidos completados (estado 8)
            $topClients = DB::table('pedidos')
                ->join('clientes', 'pedidos.id_cliente', '=', 'clientes.id')
                ->join('pedido_trackings', function ($join) {
                    $join->on('pedidos.id', '=', 'pedido_trackings.pedido_id')
                        ->whereRaw('pedido_trackings.id IN (
                            SELECT MAX(pt2.id) 
                            FROM pedido_trackings pt2 
                            WHERE pt2.pedido_id = pedidos.id
                        )');
                })
                ->where('pedido_trackings.estado', 8) // Solo pedidos completados
                ->select(
                    'clientes.id',
                    DB::raw('CONCAT(clientes.nombre, " ", clientes.apellido) as nombre'),
                    'clientes.email',
                    'clientes.celular',
                    DB::raw('COUNT(pedidos.id) as total_pedidos')
                )
                ->groupBy('clientes.id', 'clientes.nombre', 'clientes.apellido', 'clientes.email', 'clientes.celular')
                ->orderBy('total_pedidos', 'desc')
                ->limit(20)
                ->get();

            // Obtener los descuentos activos para estos clientes
            $clienteIds = $topClients->pluck('id')->toArray();
            $descuentosActivos = DescuentoCliente::whereIn('id_cliente', $clienteIds)
                ->where('estado', 1)
                ->where(function ($query) {
                    $query->where('fecha_fin', '>=', now()->toDateString())
                        ->orWhereNull('fecha_fin');
                })
                ->get()
                ->keyBy('id_cliente');

            // Añadir información de descuentos a cada cliente
            foreach ($topClients as $cliente) {
                $cliente->tiene_descuento_activo = isset($descuentosActivos[$cliente->id]);
                $cliente->descuento = $cliente->tiene_descuento_activo ? $descuentosActivos[$cliente->id] : null;
            }

            return response()->json([
                'status' => 'success',
                'data' => $topClients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los clientes top: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de uso de descuentos
     */
    public function getEstadisticasDescuentos()
    {
        try {
            // Total de descuentos creados
            $totalDescuentos = DescuentoCliente::count();

            // Descuentos activos
            $descuentosActivos = DescuentoCliente::where('estado', 1)
                ->where(function ($query) {
                    $query->where('fecha_fin', '>=', now()->toDateString())
                        ->orWhereNull('fecha_fin');
                })
                ->count();

            // Descuentos por tipo
            $descuentosPorTipo = DescuentoCliente::select('tipo_descuento', DB::raw('count(*) as total'))
                ->groupBy('tipo_descuento')
                ->get();

            // Descuentos más utilizados
            $descuentosMasUtilizados = DescuentoCliente::orderBy('cantidad_usos', 'desc')
                ->with('cliente')
                ->limit(5)
                ->get();

            // Ahorro total generado
            $ahorroTotal = DB::table('pedidos')
                ->join('descuentos_aplicados', 'pedidos.id', '=', 'descuentos_aplicados.pedido_id')
                ->sum('descuentos_aplicados.monto_descuento');

            return response()->json([
                'status' => 'success',
                'estadisticas' => [
                    'total_descuentos' => $totalDescuentos,
                    'descuentos_activos' => $descuentosActivos,
                    'descuentos_por_tipo' => $descuentosPorTipo,
                    'descuentos_mas_utilizados' => $descuentosMasUtilizados,
                    'ahorro_total' => $ahorroTotal
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar clientes por documento o nombre
     */
    public function buscarClientes(Request $request)
    {
        $query = $request->get('query', '');

        $clientes = DB::table('clientes')
            ->select(
                'clientes.id',
                DB::raw('CONCAT(clientes.nombre, " ", clientes.apellido) as nombre'),
                'clientes.email',
                'clientes.celular',
                'clientes.documento',
                DB::raw('(
                SELECT COUNT(*) FROM pedidos 
                JOIN pedido_trackings ON pedidos.id = pedido_trackings.pedido_id 
                WHERE pedidos.id_cliente = clientes.id 
                AND pedido_trackings.estado = 8
            ) as total_pedidos')
            )
            ->where(function ($q) use ($query) {
                $q->where('clientes.documento', 'LIKE', "%{$query}%")
                    ->orWhere('clientes.nombre', 'LIKE', "%{$query}%")
                    ->orWhere('clientes.apellido', 'LIKE', "%{$query}%")
                    ->orWhere(DB::raw('CONCAT(clientes.nombre, " ", clientes.apellido)'), 'LIKE', "%{$query}%");
            })
            // Removido el filtro ->having('total_pedidos', '>', 0) para incluir clientes sin pedidos
            ->orderBy('total_pedidos', 'desc')
            ->limit(10)
            ->get();

        // Verificar si tienen descuentos activos
        $clienteIds = $clientes->pluck('id')->toArray();
        $descuentosActivos = DescuentoCliente::whereIn('id_cliente', $clienteIds)
            ->where('estado', 1)
            ->where(function ($query) {
                $query->where('fecha_fin', '>=', now()->toDateString())
                    ->orWhereNull('fecha_fin');
            })
            ->get()
            ->keyBy('id_cliente');

        foreach ($clientes as $cliente) {
            $cliente->tiene_descuento_activo = isset($descuentosActivos[$cliente->id]);
            $cliente->descuento = $cliente->tiene_descuento_activo ? $descuentosActivos[$cliente->id] : null;
        }

        return response()->json([
            'status' => 'success',
            'data' => $clientes
        ]);
    }

    public function validarCodeDescuento($codigo, $idCliente)
    {
        $descuento = DescuentoCliente::where('id_cliente', $idCliente)
            ->where('codigo', $codigo)
            ->where('estado', 1)
            ->first();

        if (!$descuento) {
            return response()->json([
                'success' => false,
                'message' => 'El código no es válido',
            ], 404);
        }

        if ($descuento->usos_disponibles == 0) {
            return response()->json([
                'success' => false,
                'message' => 'El código ya se usó',
            ], 400);
        }

        // usar Carbon para comparar fechas correctamente
        $hoy = Carbon::now()->startOfDay();
        $inicio = Carbon::parse($descuento->fecha_inicio)->startOfDay();
        $fin = Carbon::parse($descuento->fecha_fin)->endOfDay();

        // si hoy es anterior al inicio o posterior al fin -> inválido
        if ($hoy->lt($inicio) || $hoy->gt($fin)) {
            return response()->json([
                'success' => false,
                'message' => 'El código ya no se encuentra disponible',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'tipo' => $descuento->tipo_descuento,
            'valor' => $descuento->valor,
        ]);
    }
}
