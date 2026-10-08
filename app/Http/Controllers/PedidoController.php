<?php

namespace App\Http\Controllers;

use App\Mail\PedidoEntregadoMail;
use App\Models\Adicional;
use App\Models\BusinessRegistration;
use App\Models\Chat;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\DescuentoCliente;
use App\Models\Establecimiento;
use App\Models\Menu;
use App\Models\Negocio;
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\PerfilNegocio;
use App\Models\Rating;
use App\Models\RepartoRegistro;
use App\Services\CoordenadasService;
use App\Services\FirebaseService;
use App\Services\HorarioService;
use App\Services\PedidoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller
{

    private $firebaseService;
    private $pedidoService;
    private $horarioService;
    private $coordenadasService;

    public function __construct(FirebaseService $firebaseService, PedidoService $pedidoService, HorarioService $horarioService, CoordenadasService $coordenadasService)
    {
        $this->coordenadasService = $coordenadasService;
        $this->firebaseService = $firebaseService;
        $this->pedidoService = $pedidoService;
        $this->horarioService = $horarioService;
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->only([
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
                'paga_con',
            ]);

            // GPS real del teléfono del cliente (opcional, distinto del punto de
            // entrega). Solo se guarda si es numérico y cae dentro de Perú.
            $gpsLat = $request->input('cliente_latitud');
            $gpsLng = $request->input('cliente_longitud');
            if (is_numeric($gpsLat) && is_numeric($gpsLng)
                && $this->coordenadasService->enPeru((float) $gpsLat, (float) $gpsLng)) {
                $data['cliente_latitud'] = (float) $gpsLat;
                $data['cliente_longitud'] = (float) $gpsLng;
            }

            // La app envía una clave única por intento de confirmación. Si la
            // conexión se corta después de crear el pedido pero antes de que la
            // respuesta le llegue al cliente, un reintento AUTOMÁTICO llega con la
            // MISMA clave: devolvemos el pedido ya creado en vez de duplicarlo.
            $claveIdempotencia = $request->input('clave_idempotencia');
            if ($claveIdempotencia) {
                $pedidoExistente = Pedido::where('clave_idempotencia', $claveIdempotencia)->first();
                if ($pedidoExistente) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'success',
                        'pedido_id' => $pedidoExistente->id,
                        'requiere_confirmacion' => (bool) $pedidoExistente->requiere_confirmacion_local,
                        'precio_delivery' => $pedidoExistente->precio_delivery ?? 0,
                    ]);
                }
                $data['clave_idempotencia'] = $claveIdempotencia;
            }

            // Número de contacto validado: lo piden las apps nuevas (exige_validacion) o todos si la
            // bandera está activa. Con la cuota de WhatsApp agotada no se puede validar, así que no se exige.
            // TODO(legacy-validacion): exigirlo siempre cuando ya no queden apps sin validación.
            if ($request->filled('id_cliente')
                && ($request->boolean('exige_validacion') || config('services.whatsapp.exigir_validacion'))) {
                $clientePedido = \App\Models\Cliente::find($request->id_cliente);
                if ($clientePedido && !$clientePedido->numeroEstaValidado()
                    && !app(\App\Services\WhatsappCloudService::class)->cuotaAgotada()) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'numero_no_validado',
                        'message' => 'Valida tu número de celular para poder hacer pedidos.',
                    ], 422);
                }
            }

            // Cliente con deuda pendiente: no puede hacer pedidos nuevos hasta regularizarla.
            if ($request->filled('id_cliente')) {
                $resumenDeuda = \App\Models\ClienteDeuda::resumenPendiente((int) $request->id_cliente);
                if ($resumenDeuda['tiene_deuda']) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'deuda_pendiente',
                        'message' => 'Tienes una deuda pendiente de S/ ' . number_format($resumenDeuda['total_adeudado'], 2)
                            . '. No puedes realizar nuevos pedidos hasta regularizarla. Comunícate con Soporte.',
                        'total_adeudado' => $resumenDeuda['total_adeudado'],
                        'deudas' => $resumenDeuda['deudas'],
                    ], 422);
                }
            }

            // Salvavidas por ventana de tiempo: cubre tanto a las apps sin
            // actualizar (nunca mandan clave) como un toque MANUAL de "Confirmar"
            // en la app nueva tras un error (genera una clave nueva, así que la
            // comprobación de arriba no la reconoce). Si hace menos de 2 minutos
            // el mismo cliente ya creó un pedido idéntico al mismo local, es casi
            // seguro un reintento y no un pedido nuevo.
            if ($request->id_cliente && $request->id_local) {
                $subtotalSanitizado = preg_replace('/[^\d.]/', '', (string) ($data['subtotal'] ?? '0'));
                $pedidoReciente = Pedido::where('id_cliente', $request->id_cliente)
                    ->where('id_local', $request->id_local)
                    ->where('subtotal', $subtotalSanitizado === '' ? 0 : $subtotalSanitizado)
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->whereDoesntHave('trackings', function ($q) {
                        $q->where('estado', 0); // cancelado
                    })
                    ->latest('id')
                    ->first();

                if ($pedidoReciente) {
                    DB::rollBack();
                    Log::warning('Pedido duplicado evitado (mismo cliente/local/monto en menos de 2 min)', [
                        'pedido_existente' => $pedidoReciente->id,
                        'id_cliente' => $request->id_cliente,
                        'id_local' => $request->id_local,
                    ]);
                    return response()->json([
                        'status' => 'success',
                        'pedido_id' => $pedidoReciente->id,
                        'requiere_confirmacion' => (bool) $pedidoReciente->requiere_confirmacion_local,
                        'precio_delivery' => $pedidoReciente->precio_delivery ?? 0,
                    ]);
                }
            }

            // Congelar la dirección de entrega vigente del cliente en el momento
            // de crear el pedido. Así, si el cliente la cambia después, los
            // pedidos ya creados no se ven afectados (motorizado y socio deben
            // leer esta columna, no la dirección actual del cliente).
            // TODO(legacy-direcciones): hacer id_direccion obligatorio y quitar el uso de la dirección vigente cuando todas las apps lo envíen
            // Apps nuevas: mandan id_direccion (la dirección elegida entre varias). Apps antiguas:
            // no lo mandan y se usa la dirección vigente (la activa, que para quien tiene una sola
            // es siempre la misma).
            $direccionElegida = $request->filled('id_direccion');
            if ($direccionElegida) {
                $clienteDireccionActual = ClienteDireccion::where('id', $request->id_direccion)
                    ->where('id_cliente', $request->id_cliente)
                    ->first();
                if (!$clienteDireccionActual) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'La dirección elegida ya no existe. Elige otra dirección e inténtalo de nuevo.',
                    ], 422);
                }
            } else {
                $clienteDireccionActual = ClienteDireccion::vigente($request->id_cliente);
            }

            if ($clienteDireccionActual) {
                $data['direccion'] = $clienteDireccionActual->direccion;
                $data['referencia'] = $clienteDireccionActual->referencia;
                if ($direccionElegida) {
                    // Liga el pedido a la dirección (y a su versión) para las notas de la casa
                    $data['id_direccion'] = $clienteDireccionActual->id;
                    $data['direccion_version'] = $clienteDireccionActual->version;
                }

                // La dirección guardada es la fuente de verdad también para las COORDENADAS.
                // El cliente edita su dirección en el mismo registro (desde la app o la web)
                // y el teléfono puede conservar una posición vieja; si se tomara la que envía
                // la app, el pedido quedaría con la dirección nueva y el punto de la anterior
                // (el repartidor navegaría al lugar equivocado). Además cubre las apps que
                // mandan 0,0 o latitud/longitud invertidas.
                if ($clienteDireccionActual->coordenadas) {
                    $c = $this->coordenadasService->desdeDireccion($clienteDireccionActual);
                    if ($c && $c['valida']) {
                        $data['latitud'] = $c['lat'];
                        $data['longitud'] = $c['lng'];
                    }
                }
            }

            // Sanitizar campos decimales
            foreach (['precio_delivery', 'descuento', 'subtotal', 'paga_con'] as $field) {
                if (isset($data[$field])) {
                    if ($field === 'paga_con' && $data[$field] === 'exacto') {
                        $data[$field] = 0;
                    } else {
                        $data[$field] = preg_replace('/[^\d.]/', '', (string)$data[$field]);
                        if ($data[$field] === '') $data[$field] = 0;
                    }
                }
            }

            $pedido = Pedido::create($data);

            if (!$pedido) {
                throw new \Exception('Error al crear el registro del pedido en la base de datos');
            }

            $totalPedido = 0;

            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $item) {
                    $precioItem = preg_replace('/[^\d.]/', '', (string)($item['price'] ?? '0'));
                    $cantidadItem = $item['quantity'] ?? 1;
                    $totalPedido += (double)$precioItem * $cantidadItem;

                    PedidoDetalle::create([
                        'pedido_id' => $pedido->id,
                        'id_producto' => $item['id'] ?? 0,
                        'nombre' => $item['name'] ?? 'Producto',
                        'cantidad' => $cantidadItem,
                        'precio' => $precioItem,
                        'tipo' => 'item',
                    ]);

                    if (isset($item['selectedAdicionales']) && is_array($item['selectedAdicionales'])) {
                        foreach ($item['selectedAdicionales'] as $grupo) {
                            if (isset($grupo['items']) && is_array($grupo['items'])) {
                                foreach ($grupo['items'] as $adicional) {
                                    $precioAdic = preg_replace('/[^\d.]/', '', (string)($adicional['precio'] ?? $adicional['pivot']['precio'] ?? '0'));
                                    $totalPedido += (double)$precioAdic * $cantidadItem;

                                    PedidoDetalle::create([
                                        'pedido_id' => $pedido->id,
                                        'id_producto' => $adicional['id'] ?? 0,
                                        'nombre' => $adicional['titulo'] ?? $adicional['name'] ?? 'Adicional',
                                        'cantidad' => $cantidadItem,
                                        'precio' => $precioAdic,
                                        'tipo' => 'adicional',
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            if ($request->has('adicionales') && is_array($request->adicionales)) {
                foreach ($request->adicionales as $adicional) {
                    $precioAdicGlobal = preg_replace('/[^\d.]/', '', (string)($adicional['price'] ?? '0'));
                    $totalPedido += (double)$precioAdicGlobal;

                    PedidoDetalle::create([
                        'pedido_id' => $pedido->id,
                        'id_producto' => $adicional['id'] ?? 0,
                        'nombre' => $adicional['name'] ?? 'Adicional',
                        'cantidad' => 1,
                        'precio' => $precioAdicGlobal,
                        'tipo' => 'adicional',
                    ]);
                }
            }

            $comercio = BusinessRegistration::find($request->id_local);
            if (!$comercio) {
                throw new \Exception("Comercio no encontrado (ID: {$request->id_local})");
            }

            $pedido->subtotal = $totalPedido;

            $requiereConfirmacion = $totalPedido >= 100;
            if ($comercio->omitir_pago_adelantado) {
                $requiereConfirmacion = false;
            }

            $pedido->requiere_confirmacion_local = $requiereConfirmacion;
            $pedido->tipo_pedido = $request->tipo_entrega == 'delivery' ? 0 : 1;

            if ($request->codigo) {
                $descuento = DescuentoCliente::where('id_cliente', $request->id_cliente)
                    ->where('codigo', $request->codigo)
                    ->where('estado', 1)
                    ->first();

                if ($descuento) {
                    $descuento->usos_disponibles = max(0, $descuento->usos_disponibles - 1);
                    if ($descuento->usos_disponibles == 0) $descuento->estado = 0;
                    $descuento->cantidad_usos = ($descuento->cantidad_usos ?? 0) + 1;
                    $descuento->save();
                }
            }

            PedidoTracking::create([
                'pedido_id' => $pedido->id, 
                'estado' => 1,
                'user_id' => $request->id_cliente,
                'user_type' => 'cliente'
            ]);
            $pedido->save();

            DB::commit();

            // Notificaciones enviadas DESPUÉS de responder al cliente: FCM puede
            // tardar, y mientras la app espera esa respuesta es más fácil que se
            // corte la conexión (lo que antes hacía creer que el pedido había
            // fallado y llevaba a reintentarlo, duplicándolo).
            $pedidoId = $pedido->id;
            $idCliente = $request->id_cliente;
            dispatch(function () use ($comercio, $pedidoId, $idCliente) {
                try {
                    $cliente = Cliente::find($idCliente);
                    $nombreCliente = $cliente ? $cliente->nombre : 'Cliente';
                    $tituloNotif = '🛒 Nuevo Pedido de ' . $nombreCliente;
                    $cuerpoNotif = 'El pedido #' . $pedidoId . ' ya está disponible para procesar.';

                    if ($comercio->token_fmc && $comercio->activo == 1) {
                        $this->firebaseService->sendNotificationWithSound($comercio->token_fmc, $tituloNotif, $cuerpoNotif, 'nuevo_pedido', 'pedidos_v3', [], 'socio', $comercio->id, 'socio');
                    }

                    if ($comercio->token_fmc_web && $comercio->activo == 1) {
                        $this->firebaseService->sendNotificationWithSound($comercio->token_fmc_web, $tituloNotif, $cuerpoNotif, 'nuevo_pedido', 'pedidos_v3', [], 'socio_web', $comercio->id, 'socio');
                    }
                } catch (\Exception $e) {
                    Log::warning("Error enviando notificación: " . $e->getMessage());
                }
            })->afterResponse();

            return response()->json([
                'status' => 'success',
                'pedido_id' => $pedido->id,
                'requiere_confirmacion' => $requiereConfirmacion,
                'precio_delivery' => $request->precio_delivery ?? 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error en store pedido: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error al procesar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }


    public function calcularPrecioDelivery(Request $request, $idLocal, $idCliente)
    {
        $local = Establecimiento::where('business_registration_id', $idLocal)->first();
        // TODO(legacy-direcciones): exigir id_direccion y quitar el fallback a la vigente

        // ?id_direccion=: la app nueva calcula el envío para la dirección elegida; sin él, la vigente
        $clienteDireccion = $request->filled('id_direccion')
            ? ClienteDireccion::where('id', $request->query('id_direccion'))->where('id_cliente', $idCliente)->first()
            : ClienteDireccion::vigente($idCliente);
        $c = $clienteDireccion ? $this->coordenadasService->desdeDireccion($clienteDireccion) : null;
        if (!$c || !$c['valida']) {
            return response()->json(['error' => 'El cliente no tiene una dirección con coordenadas válidas'], 422);
        }

        // Normalizar precisión: 6 decimales (≈0.11 m), usa 5 si quieres más tolerancia
        $lat1 = round((float) $local->latitud, 6);
        $lon1 = round((float) $local->longitud, 6);
        $lat2 = round($c['lat'], 6);
        $lon2 = round($c['lng'], 6);

        // Google Distance: origen=cliente, destino=local (mismo sentido que getLocales/buscador)
        $googleDistancias = $this->pedidoService->obtenerDistanciaGoogle($lat2, $lon2, [
            ['lat' => $lat1, 'lng' => $lon1]
        ]);
        $distanciaGoogle = $googleDistancias[0] ?? null;

        // Usar Google Distance (consistente con buscador), fallback a Haversine si Google falla
        $distancia = $distanciaGoogle
            ?: $this->pedidoService->calcularDistanciaHaversine($lat1, $lon1, $lat2, $lon2);

        $precio_delivery = $distancia ? $this->pedidoService->calcularPrecioPorDistancia($distancia, $idLocal) : 0;

        // Formatear con 2 decimales
        return response()->json(number_format((float) $precio_delivery, 2, '.', ''));
    }

    public function pruebaNoticacion(Request $request)
    {
        Log::info("[FCM-DIAG][pruebaNoticacion] sonido=" . json_encode($request->sonido)
            . " channel_id=" . json_encode($request->channel_id)
            . " token=" . (is_string($request->token) && strlen($request->token) > 20
                ? substr($request->token, 0, 10) . '...' . substr($request->token, -8)
                : json_encode($request->token)));
        if ($request->sonido == 'true') {
            $this->firebaseService->sendNotificationWithSound($request->token, 'Prueba', 'Notificacion con sonido', 'nuevo_pedido', $request->channel_id);
        } else {
            $this->firebaseService->sendNotification($request->token, 'Prueba', 'Notificacion sin sonido', [], 'prueba', 26, 'prueba');
        }
    }

    public function testLiveActivity(Request $request)
    {
        $id_pedido = (string)($request->id_pedido ?? '974');
        $estado = (string)($request->estado ?? '2');
        $token = $request->token; // El token debe ser enviado desde Postman o cliente
        $tiempo = (string)($request->tiempo ?? '30');
        $progress = (string)($request->progress ?? progresoPedido($estado));

        $data = [
            'type' => 'order_status_update',
            'order_id' => $id_pedido,
            'progress' => $progress,
            'tiempo' => $tiempo,
            'tel_motorizado' => '999888777',
        ];

        return $this->firebaseService->sendNotification(
            $token,
            estadoPedido($estado),
            mensajeNotificacionPedido($estado, $id_pedido, 'cliente'),
            $data,
            'cliente',
            null,
            'cliente'
        );
    }

    /**
     * Avisa de un pedido nuevo a todos los motorizados con estado 1 y activo 1, sin
     * importar su ubicación. Los avisos se envían en paralelo para que lleguen a la vez.
     */
    public function sendMotorizadosCerca()
    {
        $destinatarios = [];
        $activos = $this->pedidoService->motorizadosParaAvisoDePedido();
        foreach ($activos as $motorizado) {
            if (!empty($motorizado['token'])) {
                $destinatarios[] = [
                    'token' => $motorizado['token'],
                    'userId' => $motorizado['id'],
                    'userType' => 'motorizado',
                    'appName' => 'motorizado',
                ];
            }
        }

        Log::info('[Motorizados] aviso de pedido nuevo', [
            'activos' => count($activos),
            'con_token' => count($destinatarios),
            'sin_token' => count($activos) - count($destinatarios),
        ]);

        $this->firebaseService->sendNotificationsWithSoundBatch(
            $destinatarios,
            '🛵 Nuevo Pedido Disponible',
            '📍 Un nuevo pedido está disponible. ¡No lo dejes pasar!',
            'nuevo_pedido',
            'pedidos_v7'
        );
    }

    public function iniciarViaje(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'id_motorizado' => 'required|integer',
            'estado' => 'nullable|integer'
        ]);

        $idMotorizado = (int) $request->id_motorizado;

        // Usar transacción y lock para evitar condiciones de carrera
        return DB::transaction(function () use ($idMotorizado, $request) {

            // Bloquear el pedido para evitar que dos motorizados lo tomen al mismo tiempo
            $pedido = Pedido::lockForUpdate()->find($request->id);

            if (!$pedido) {
                return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
            }

            Log::info('Intento de iniciar viaje', [
                'pedido_id' => $request->id,
                'id_motorizado_intento' => $idMotorizado,
                'id_motorizado_actual_db' => $pedido->id_motorizado
            ]);

            if ($pedido->id_motorizado) {
                if ($pedido->id_motorizado == $idMotorizado) {
                    return response()->json(['status' => 'success', 'message' => 'Ya tienes este pedido asignado']);
                }
                Log::warning('Pedido ya asignado', [
                    'pedido_id' => $request->id,
                    'motorizado_actual' => $pedido->id_motorizado,
                    'motorizado_intento' => $idMotorizado
                ]);
                return response()->json(['status' => 'error', 'message' => 'El pedido ya tiene un motorizado asignado'], 400);
            }

            $ultimoTracking = $pedido->trackings()->latest()->first();
            
            if ($ultimoTracking) {
                if ($ultimoTracking->estado == 0) {
                    return response()->json(['status' => 'error', 'message' => 'El pedido ya ha sido cancelado'], 400);
                }
                if ($ultimoTracking->estado == 8) {
                    return response()->json(['status' => 'error', 'message' => 'El pedido ya ha sido entregado'], 400);
                }
                if (!in_array($ultimoTracking->estado, [1, 2, 3])) {
                    return response()->json(['status' => 'error', 'message' => 'El pedido no está disponible para ser tomado'], 400);
                }
            }

            // Bloquear la fila del motorizado (RepartoRegistro) para la duración de la transacción
            $reparto = RepartoRegistro::where('id', $idMotorizado)->lockForUpdate()->first();

            if (!$reparto) {
                return response()->json(['status' => 'error', 'message' => 'Motorizado no encontrado'], 404);
            }

            $condicionHorario = $this->horarioService->puedeTrabajar($idMotorizado);
            if (!$condicionHorario['puede_trabajar']) {
                return response()->json(['status' => 'error', 'message' => $condicionHorario['mensaje']], 403);
            }

            $pedidos_consecutivos = (int) ($reparto->pedidos_consecutivos ?: 1);

            $puedeAceptar = $this->verificarPedidosActivosMotorizado($idMotorizado, $pedidos_consecutivos);

            if (!$puedeAceptar) {
                return response()->json(['status' => 'error', 'message' => 'El motorizado ha alcanzado el límite de pedidos activos'], 400);
            }

            // Asignar y guardar dentro de la transacción
            $pedido->id_motorizado = $idMotorizado;
            $pedido->save();

            // Notificaciones (con chequeos de null)
            $local_fmc = $pedido->id_local ? BusinessRegistration::find($pedido->id_local)->token_fmc ?? null : null;
            $cliente_fmc = $pedido->id_cliente ? Cliente::find($pedido->id_cliente)->token_fmc ?? null : null;

            // La app del motorizado no envía 'estado' al iniciar el viaje: sin esto el
            // título salía null y Firebase rechazaba el aviso (HTTP 400) al local y al cliente.
            $estadoAviso = $request->estado ?? 4; // 4 = motorizado asignado
            $estado = estadoPedido($estadoAviso);
            $mensajeLocal = mensajeNotificacionPedido($estadoAviso, $pedido->id, 'local');
            $mensajeCliente = mensajeNotificacionPedido($estadoAviso, $pedido->id, 'cliente');

            if ($local_fmc) {
                $this->firebaseService->sendNotification($local_fmc, $estado, $mensajeLocal, [], 'socio', $pedido->id_local, 'socio');
            }

            // Registrar el tracking del pedido (con chequeo si no hay trackings)
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            $estadoTracking = ($pedidoTracking && $pedidoTracking->estado == 2) ? 2 : 4;
            if($cliente_fmc){
                $telMotorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado)->celular : null;
                $this->firebaseService->sendNotification(
                    $cliente_fmc,
                    $estado,
                    $mensajeCliente,
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string)$pedido->id,
                        'progress' => (string)progresoPedido($estadoTracking),
                        'tiempo' => (string)($pedido->tiempo ?? 0),
                        'tel_motorizado' => (string)($telMotorizado ?? ''),
                    ],
                    'cliente',
                    $pedido->id_cliente,
                    'cliente'
                );
            }

            PedidoTracking::create([
                'pedido_id' => $pedido->id,
                'estado' => $estadoTracking,
                'user_id' => $idMotorizado,
                'user_type' => 'motorizado'
            ]);

            return response()->json(['status' => 'success']);
        });
    }

    private function verificarPedidosActivosMotorizado(int $idMotorizado, int $pedidos_consecutivos): bool
    {
        // Asegúrate de que la relación 'trackings' exista en el modelo Pedido y que los estados usados sean correctos.
        $pedidosActivos = DB::table('pedidos as p')
            ->where('p.id_motorizado', $idMotorizado)
            ->whereDate('p.created_at', Carbon::today())
            // que tenga al menos un tracking en 2..7
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pedido_trackings as pt')
                    ->whereRaw('pt.pedido_id = p.id')
                    ->whereIn('pt.estado', [2, 3, 4, 5, 6, 7]);
            })
            // y que NO tenga ningún tracking con estado = 8 o 0 (cancelado)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pedido_trackings as pt2')
                    ->whereRaw('pt2.pedido_id = p.id')
                    ->whereIn('pt2.estado', [0, 8]);
            })
            ->count('p.id');
        // Si queremos permitir hasta N pedidos activos (es decir, si N es el máximo permitido),
        // la condición para aceptar otro pedido es que pedidosActivos < pedidos_consecutivos.
        return $pedidosActivos < $pedidos_consecutivos;
    }

    public function updateEstadoPedido(Request $request, $id)
    {
        // Verificar si el pedido existe
        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Validar transición de estado (no permitir volver atrás)
        $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest('id')->first();
        if ($ultimoTracking) {
            // Si el pedido ya está cancelado o entregado, no permitir más cambios (excepto tal vez cancelar si no lo estaba)
            if ($ultimoTracking->estado == 8 || $ultimoTracking->estado == 0) {
                 return response()->json(['error' => 'El pedido ya se encuentra en un estado final (Entregado/Cancelado)'], 400);
            }
            
            // No permitir volver a estados anteriores (ej: de 6 a 2)
            // Permitimos el mismo estado solo si es un reintento inofensivo
            // Nota: El estado 8 (Entregado) es cronológicamente posterior al 9 (Listo para recoger) 
            // aunque su ID sea menor.
            $esTransicionAEntregadoDesdeListo = ($ultimoTracking->estado == 9 && $request->estado == 8);

            if ($request->estado > 0 && $request->estado < $ultimoTracking->estado && !$esTransicionAEntregadoDesdeListo) {
                return response()->json(['error' => 'No es posible volver a un estado anterior'], 400);
            }

            // El pedido ya fue recogido por el motorizado: no se permite cancelar directamente,
            // debe solicitarse la cancelación con una justificación para que el admin la apruebe.
            if ((int) $request->estado === 0 && in_array((int) $ultimoTracking->estado, [5, 6, 7], true)) {
                return response()->json(['error' => 'El pedido ya fue recogido por el motorizado. Debe solicitar la cancelación con una justificación para que el administrador la apruebe.'], 409);
            }
        }

        // Guardar el tiempo o fecha de inicio si se envía
        if ($request->tiempo) {
            $pedido->tiempo = $request->tiempo;
        }
        if ($request->estado == 2) {
            $pedido->fecha_hora_inicio = Carbon::now();
        }
        $pedido->save();

        // Crear un nuevo tracking para el pedido
        $tracking = new PedidoTracking();
        $tracking->pedido_id = $id;
        $tracking->estado = $request->estado;
        $tracking->setTraceability($request);
        $tracking->save();

        // Al aceptar el local, avisar a los motorizados de inmediato. Se envía después
        // de responder al local para que la aceptación no espere a Firebase.
        if ($request->estado == 2 && $pedido->id_motorizado == null && ($pedido->tipo_pedido == '0' || $pedido->tipo_pedido == 0)) {
            dispatch(function () {
                try {
                    $this->sendMotorizadosCerca();
                } catch (\Throwable $e) {
                    Log::warning('Error avisando a los motorizados: ' . $e->getMessage());
                }
            })->afterResponse();
        }

        // Notificación para Live Activity en resto de estados
        if ($request->estado != 0 && $request->estado != 3) {
            $cliente = Cliente::find($pedido->id_cliente);
            if ($cliente && $cliente->token_fmc) {
                $telMotorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado)->celular : null;
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    estadoPedido($request->estado),
                    mensajeNotificacionPedido($request->estado, $pedido->id, 'cliente'),
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string)$pedido->id,
                        'progress' => (string)progresoPedido($request->estado),
                        'tiempo' => (string)($pedido->tiempo ?? 0),
                        'tel_motorizado' => (string)($telMotorizado ?? ''),
                    ],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            }
        }

        if ($request->estado == 3 && $pedido->id_motorizado !== null && ($pedido->tipo_pedido == '0' || $pedido->tipo_pedido == 0)) {
            $tracking = new PedidoTracking();
            $tracking->pedido_id = $id;
            $tracking->estado = 4;
            $tracking->setTraceability($request);

            $biker = RepartoRegistro::where('id', $pedido->id_motorizado)->first();
            if ($biker->token_fmc) {
                $this->firebaseService->sendNotification(
                    $biker->token_fmc,
                    'Hola ' . $biker->nombre,
                    'El restauranete termino de preparar el pedido #' . $pedido->id . '. Por favor, retíralo.',
                    [],
                    'motorizado',
                    $biker->id,
                    'motorizado'
                );
            }

            // Buscar el último tracking creado para este pedido
            $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest('created_at')->first();
            if ($ultimoTracking) {
                // Sumarle 30 segundos al created_at del último tracking
                $nuevoCreatedAt = (clone $ultimoTracking->created_at)->addSeconds(30);
                $tracking->created_at = $nuevoCreatedAt;
                $tracking->updated_at = $nuevoCreatedAt;
            }
            $tracking->save();
        }

        if ($request->estado == 3 && ($pedido->tipo_pedido == '1' || $pedido->tipo_pedido == 1)) {
            $tracking = new PedidoTracking();
            $tracking->pedido_id = $id;
            $tracking->estado = 9;
            $tracking->setTraceability($request);

            $cliente = Cliente::where('id', $pedido->id_cliente)->first();
            if ($cliente->token_fmc) {
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    'Hola ' . $cliente->nombre,
                    'El restaurante termino de preparar el pedido #' . $pedido->id . '. Por favor, retíralo.',
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string)$pedido->id,
                        'progress' => (string)progresoPedido(9),
                    ],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            }

            // Buscar el último tracking creado para este pedido
            $ultimoTracking = PedidoTracking::where('pedido_id', $id)->latest('created_at')->first();
            if ($ultimoTracking) {
                // Sumarle 30 segundos al created_at del último tracking
                $nuevoCreatedAt = (clone $ultimoTracking->created_at)->addSeconds(30);
                $tracking->created_at = $nuevoCreatedAt;
                $tracking->updated_at = $nuevoCreatedAt;
            }
            $tracking->save();
        }

        if ($request->estado == 0) {
            $cliente = Cliente::where('id', $pedido->id_cliente)->first();
            if ($cliente->token_fmc) {
                $this->firebaseService->sendNotification(
                    $cliente->token_fmc,
                    'Hola ' . $cliente->nombre,
                    'Tu pedido #' . $pedido->id . ' ha sido cancelado por el restaurante.',
                    [
                        'type' => 'order_status_update',
                        'order_id' => (string)$pedido->id,
                        'progress' => (string)progresoPedido(0),
                    ],
                    'cliente',
                    $cliente->id,
                    'cliente'
                );
            }
            if ($pedido->id_motorizado) {
                $biker = RepartoRegistro::find($pedido->id_motorizado);
                if ($biker && $biker->token_fmc) {
                    $this->firebaseService->sendNotification(
                        $biker->token_fmc,
                        'Pedido Cancelado',
                        'El restaurante ha cancelado el pedido #' . $pedido->id . '.',
                        [],
                        'motorizado',
                        $biker->id,
                        'motorizado'
                    );
                }
            }
        }

        // Retornar respuesta exitosa
        return response()->json(['message' => 'Estado actualizado correctamente'], 200);
    }

    public function getLocalYcustomerPosition($idPedido)
    {
        // Verificar si el pedido existe
        $pedido = Pedido::find($idPedido);
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        // Usar las coordenadas propias del pedido (congeladas al crearlo), no la
        // dirección actual del cliente: si el cliente la cambió después, este
        // pedido debe seguir apuntando a donde realmente se hizo la entrega.
        $resp = [
            'locallat' => $local->latitud,
            'locallon' => $local->longitud,
            'custlat' => $pedido->latitud,
            'custlon' => $pedido->longitud,
            // GPS real del teléfono del cliente al pedir (null si no lo compartió)
            'cliente_lat' => $pedido->cliente_latitud,
            'cliente_lon' => $pedido->cliente_longitud,
        ];
        // Retornar respuesta exitosa
        return response()->json($resp);
    }

    /**
     * La app del cliente envía su GPS mientras el pedido va en camino, para que
     * el repartidor lo vea moverse. Solo acepta al cliente dueño del pedido,
     * posiciones dentro de Perú y pedidos de delivery aún activos.
     */
    public function actualizarUbicacionCliente(Request $request)
    {
        $request->validate([
            'id_pedido' => 'required|integer',
            'id_cliente' => 'required|integer',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
        ]);

        $pedido = Pedido::find($request->id_pedido);
        if (!$pedido || (int) $pedido->id_cliente !== (int) $request->id_cliente) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $lat = (float) $request->latitud;
        $lng = (float) $request->longitud;
        if (!$this->coordenadasService->enPeru($lat, $lng)) {
            return response()->json(['error' => 'Ubicación fuera de rango'], 422);
        }

        // Solo mientras el repartidor está en camino (asignado hasta antes de entregar)
        $ultimo = PedidoTracking::where('pedido_id', $pedido->id)->latest('id')->first();
        if (!$ultimo || !in_array((int) $ultimo->estado, [4, 5, 6, 7], true)) {
            return response()->json(['status' => 'ignorado']);
        }

        $pedido->cliente_latitud = $lat;
        $pedido->cliente_longitud = $lng;
        $pedido->save();

        return response()->json(['status' => 'ok']);
    }

    public function obtenerUbicacionCliente($idPedido)
    {
        $pedido = Pedido::find($idPedido);
        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'cliente_lat' => $pedido->cliente_latitud,
            'cliente_lon' => $pedido->cliente_longitud,
        ]);
    }

    public function getPedidosCliente($idCliente)
    {
        $data = [];
        $pedidos = Pedido::where('id_cliente', $idCliente)->get();
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
            if ($pedidoTracking && $local) {
                $logo = PerfilNegocio::where('business_registration_id', $pedido->id_local)->first();
                $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                $total = $pedidoDetalles->sum(function ($detalle) {
                    return $detalle->precio * $detalle->cantidad;
                });
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
                    'logo' => $logo->ruta_logo ? config('app.url') . '/' . $logo->ruta_logo : 'https://magusemail.com/truelove-back/public/default_avatar.png',
                    'total' => $total,
                    'cantidad' => count($pedidoDetalles),
                    'items' => $pedidoDetalles->map(function ($detalle) {
                        return [
                            'nombre' => $detalle->nombre,
                            'cantidad' => $detalle->cantidad,
                            'precio' => $detalle->precio
                        ];
                    }),
                    'direccion' => $pedido->direccion ?? (ClienteDireccion::vigente($pedido->id_cliente)?->direccion ?? ''),
                    'created_at' => $pedido->created_at,
                    'requiere_confirmacion_local' => $pedido->requiere_confirmacion_local == 1 ? true : false,
                    'existeCalificacion' => $existeCalificacion,
                    'tipo_pedido' => $pedido->tipo_pedido,
                    'paga_con' => $pedido->paga_con,
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
            'foto' => $motorizado->foto_perfil_url,
            'vehiculo' => $motorizado->vehiculo ?? 'Sin vehículo registrado',
            'placa' => $motorizado->registroVehiculo ? $motorizado->registroVehiculo->placa : 'S/P',
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
            if ($pedidoTracking->estado == 8) {
                $rating = Rating::where('id_pedido', $pedido->id)->first();
                if ($rating) {
                    $cliente = Cliente::where('id', $pedido->id_cliente)->first();
                    $coment[] = [
                        'id' => $pedido->id,
                        'comentario' => $rating->motorcycle_comment ?? 'No hay comentario',
                        'rating' => number_format($rating->motorcycle_rating, 1, '.', ''),
                        'cliente' => $cliente ? ($cliente->nombre . ' ' . $cliente->apellido) : 'Cliente no encontrado',
                    ];
                }
            }
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

            if ($rating && $cliente) {
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
        $pedido->total = PedidoDetalle::where('pedido_id', $pedido->id)->sum(DB::raw('precio * cantidad'));
        
        $ultimoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
        $pedido->fecha_entrega = $ultimoTracking ? $ultimoTracking->created_at : $pedido->created_at;

        $cliente = Cliente::find($pedido->id_cliente);
        $pedido->cliente = $cliente ? $cliente->only(['nombre', 'apellido', 'email']) : null;

        $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado) : null;
        $pedido->motorizado = $motorizado ? $motorizado->only(['nombres', 'apellidos', 'celular']) : null;

        if ($pedido->cliente && isset($pedido->cliente['email'])) {
            Mail::to($pedido->cliente['email'])->send(new PedidoEntregadoMail($pedido));
        }

        return response()->json(['message' => 'Correo enviado con éxito']);
    }

    public function getPedido($id)
    {
        $pedido = Pedido::with([
            'trackings' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $cliente = Cliente::find($pedido->id_cliente);
        $clienteDireccion = ClienteDireccion::vigente($pedido->id_cliente);
        $motorizado = RepartoRegistro::find($pedido->id_motorizado);

        $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
        $detalleNombres = $pedidoDetalles->pluck('nombre')->implode(', ');

        $ultimoTracking = $pedido->trackings->first();
        $estadoTracking = $ultimoTracking ? estadoPedido($ultimoTracking->estado) : 'Sin seguimiento';

        $pedido->motorizado = $motorizado ? trim(($motorizado->nombres ?? '') . ' ' . ($motorizado->apellidos ?? '')) : '';
        $pedido->celular_motorizado = $motorizado->celular ?? '';
        $pedido->foto_motorizado = $motorizado ? $motorizado->foto_perfil_url : null;

        $pedido->detalle = $detalleNombres;
        $pedido->detalleArray = $pedidoDetalles;
        $pedido->ultimo_estado_tracking = $ultimoTracking->estado ?? 'Sin seguimiento';
        $pedido->estado = $estadoTracking;

        $pedido->local = $local->nombre_establecimiento ?? '';
        $pedido->direccion_local = $local->direccion_completa ?? '';
        $pedido->direccion_entrega = $pedido->direccion ?? ($clienteDireccion?->direccion ?? '');
        $pedido->cliente = $cliente ? "{$cliente->nombre} {$cliente->apellido}" : '';
        $pedido->celular = $cliente->celular ?? '';
        $pedido->celular_whatsapp = ($cliente && $cliente->celular_whatsapp && $cliente->celular_whatsapp !== $cliente->celular) ? $cliente->celular_whatsapp : null;
        $pedido->lat_local = $local->latitud ?? '';
        $pedido->lon_local = $local->longitud ?? '';
        $pedido->tiempo = $pedido->tiempo ?? 0;
        if ($pedido->fecha_hora_inicio) {
            $pedido->fecha_inicio = $pedido->fecha_hora_inicio?->toIso8601String();
            $pedido->fecha_hora_inicio = $pedido->fecha_hora_inicio?->toIso8601String();
        } else {
            $trackingInicio = PedidoTracking::where('pedido_id', $pedido->id)->where('estado', 2)->first();
            $pedido->fecha_inicio = $trackingInicio ? $trackingInicio->created_at?->toIso8601String() : null;
            $pedido->fecha_hora_inicio = $pedido->fecha_inicio;
        }

        return response()->json($pedido);
    }

    public function mandarAlertaDeAuxilio(Request $request)
    {
        $pedido = Pedido::find($request->id_pedido);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado) : null;
        if (!$motorizado) {
            return response()->json(['error' => 'Motorizado no asignado'], 400);
        }

        $motorizadoData = $motorizado->only(['nombres', 'apellidos', 'celular']);
        $nombre = $motorizadoData['nombres'] . ' ' . $motorizadoData['apellidos'];
        $motorizados = RepartoRegistro::where('estado', 1)->where('aprobado', 1)->get();
        
        foreach ($motorizados as $motorizado) {
            if ($motorizado->token_fmc) {
                $this->firebaseService->sendNotification(
                    $motorizado->token_fmc, 
                    '🛵 Alerta!', 
                    "📍 El motorizado {$nombre} todavia no finaliza su viaje",
                    [],
                    'motorizado',
                    $motorizado->id,
                    'motorizado'
                );
            }
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
                'id' => $menu->id,
                'name' => $menu->titulo,
                'category' => $menu->descripcion,
                'image' => $menu->foto,
                'price' => $menu->precio,
                'discountedPrice' => 0,
                'quantity' => $detalle->cantidad,
            ];
        }

        $data = [
            'idLocal' => $pedido->id_local,
            'adicionales' => [],
            'items' => $items,
            'nota' => $pedido->nota ?? '',
            'precio_delivery' => $pedido->precio_delivery,
        ];

        return response()->json($data);
    }

    public function verificarConfirmacion($id)
    {
        $pedido = Pedido::findOrFail($id);
        $negocio = Negocio::where('business_registration_id', $pedido->id_local)->first();
        $businessRegistration = BusinessRegistration::where('id', $pedido->id_local)->first();
        $telefono = $negocio->numero_pago_digital ?? '';
        $telefono = $negocio->numero_pago_digital ? formatPhoneNumber($negocio->numero_pago_digital) : formatPhoneNumber($negocio->telefono);
        $tipoPago = 'Ninguno';
        switch ($negocio->tipo_pago_digital) {
            case 1:
                $tipoPago = 'Yape';
                break;
            case 2:
                $tipoPago = 'Plin';
                break;
        }
        $estadoPedido = PedidoTracking::where('pedido_id', $id)->latest()->first()->estado ?? 1;
        return response()->json([
            'requiere_confirmacion' => $pedido->requiere_confirmacion_local,
            'numero_local' => $telefono,
            'tipo_pago_digital' => $tipoPago,
            'titular' => $negocio->nombre_titular_pago_digital ?? $businessRegistration->name . '  ' . $businessRegistration->lastName ?? '',
            // Si el negocio subió su QR de Yape/Plin, el cliente debe ver el QR
            // en vez del número; el número queda como respaldo si no hay QR.
            'qr_pago_digital' => $negocio->qr_pago_digital ? url($negocio->qr_pago_digital) : null,
            'estado' => $estadoPedido,
            'omitir_pago_adelantado' => $businessRegistration->omitir_pago_adelantado ?? false,
        ]);
    }

    public function updateVerificarConfirmacion($id)
    {
        $pedido = Pedido::findOrFail($id);
        $pedido->requiere_confirmacion_local = 0;
        $pedido->save();
        return response()->json(true);
    }

    public function prueba()
    {
        $desde = Carbon::now()->subHours(2);
        $hasta = Carbon::now();

        // Buscar pedidos entregados en las últimas 2 horas
        $pedidos = Pedido::whereBetween('created_at', [$desde, $hasta])->get();
        foreach ($pedidos as $pedido) {
            $pedidoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if ($pedidoTracking->estado == 7) {
                $motorizado = RepartoRegistro::find($pedido->id_motorizado);
                $negocio = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
                if (!$motorizado)
                    continue;
                if (!$negocio)
                    continue;

                $nombre = $motorizado->nombres;
                $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                $total = number_format($pedidoDetalles->sum(function ($detalle) {
                    return $detalle->precio * $detalle->cantidad;
                }), 2);
                $direccion = $pedido->direccion ?? 'la dirección que nos brindaste';

                $mensaje = "Hola soy {$nombre}, tú Driver de TRUE LOVE DELIVERY. Acabo de llegar con tu pedido de {$negocio->nombre_establecimiento}. El subtotal a pagar incluyendo el delivery sería S/{$total}.";

                // Guardar en la tabla chat
                Chat::create([
                    'pedido_id' => $pedido->id,
                    'sender_id' => $pedido->id_motorizado,
                    'receiver_id' => $pedido->id_cliente,
                    'message' => $mensaje,
                ]);

                echo ("Mensaje enviado al pedido #{$pedido->id}");
            }
        }
    }

    public function uploadPaymentProof(Request $request)
    {
        Log::info('Iniciando uploadPaymentProof', ['data' => $request->all()]);

        $pedido = Pedido::find($request->pedido_id);

        if (!$pedido) {
            Log::warning('Pedido no encontrado', ['pedido_id' => $request->pedido_id]);
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            Log::info('Archivo payment_proof recibido', [
                'pedido_id' => $pedido->id,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_valid' => $file->isValid()
            ]);

            // Validar que el archivo sea válido
            if (!$file->isValid()) {
                Log::error('Archivo no válido', ['pedido_id' => $pedido->id]);
                return response()->json(['error' => 'Archivo no válido'], 400);
            }

            try {
                // Verificar que el directorio existe
                $storagePath = storage_path('app/public/comprobantes');
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                    Log::info('Directorio creado', ['path' => $storagePath]);
                }

                // Generar nombre único para el archivo
                $extension = $file->getClientOriginalExtension();
                $filename = 'comprobante_' . $pedido->id . '_' . time() . '.' . $extension;

                // Guardar el archivo usando storeAs para tener más control
                $fotoPath = $file->storeAs('comprobantes', $filename, 'public');
                Log::info('Archivo guardado en storage', ['path' => $fotoPath]);

                if (!$fotoPath) {
                    Log::error('Error: storeAs() devolvió false');
                    return response()->json(['error' => 'Error al guardar el archivo'], 500);
                }

                $fotoUrl = Storage::url($fotoPath);
                Log::info('URL generada', ['url' => $fotoUrl]);

                $pedido->foto_pago = $fotoUrl;
                $pedido->save();

                Log::info('Comprobante guardado', ['pedido_id' => $pedido->id, 'foto_pago' => $fotoUrl]);
                return response()->json(['success' => true, 'foto_pago' => $fotoUrl]);
            } catch (\Exception $e) {
                Log::error('Error al guardar archivo', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Error al guardar archivo: ' . $e->getMessage()], 500);
            }
        } else {
            Log::warning('No se recibió archivo payment_proof', ['pedido_id' => $request->pedido_id]);
            return response()->json(['error' => 'No se recibió archivo'], 400);
        }
    }

    public function cancelarPedido(Request $request)
    {
        $pedido = Pedido::find($request->id_pedido);
        if (!$pedido) {
            return response()->json(['status' => 'error', 'message' => 'Pedido no encontrado'], 404);
        }

        // Solo permitir cancelar si está en estado pendiente (1)
        $ultimoTracking = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
        if (!$ultimoTracking || $ultimoTracking->estado != 1) {
            return response()->json(['status' => 'error', 'message' => 'El pedido ya no puede ser cancelado'], 400);
        }

        $tracking = new PedidoTracking([
            'pedido_id' => $pedido->id,
            'estado' => 0 // Cancelado
        ]);
        $tracking->setTraceability($request);
        $tracking->save();

        $business = BusinessRegistration::find($pedido->id_local);
        $local_fmc = $business ? $business->token_fmc : null;

        if ($local_fmc) {
            $this->firebaseService->sendNotification(
                $local_fmc,
                'Pedido cancelado',
                'El pedido #' . $pedido->id . ' ha sido cancelado por el cliente.',
                [],
                'socio',
                $pedido->id_local,
                'socio'
            );
        }

        return response()->json(['status' => 'success', 'message' => 'Pedido cancelado correctamente']);
    }
}
