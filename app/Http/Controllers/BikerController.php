<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use App\Models\BusinessRegistration;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\CuentaBancariaReparto;
use App\Models\Establecimiento;
use App\Models\HorarioAsignacion;
use App\Models\HorarioGrupo;
use App\Models\Location;
use App\Models\MedioPago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
use App\Models\RepartoRegistro;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            'repartidor' => $reparto,
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
        $reparto = RepartoRegistro::findOrFail($idMotorizado);
        if (!$reparto->activo) {
            return [];
        }

        $motorizadoLocation = Location::where('motorizado_id', $idMotorizado)
            ->latest()
            ->first();

        // Obtener los pedidos con el id_local correspondiente
        $pedidos = Pedido::whereNotNull('id_local')
            ->whereNull('id_motorizado')
            ->where('tipo_pedido', 0)
            ->whereDate('created_at', Carbon::today())
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('pedido_id'))
                    ->from('pedido_trackings')
                    ->whereRaw('estado = 2 or estado = 3')
                    ->whereIn(DB::raw('(pedido_id, created_at)'), function ($sub) {
                        $sub->select(DB::raw('pedido_id, MAX(created_at)'))
                            ->from('pedido_trackings')
                            ->groupBy('pedido_id');
                    });
            })
            ->get();

        foreach ($pedidos as $pedido) {
            // Obtener el establecimiento asociado al pedido
            $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
            $estado = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            if ($motorizadoLocation && $local) {
                // Calcular la distancia entre el motorizado y el local
                $distancia = $this->calcularDistanciaHaversine(
                    $motorizadoLocation->latitude,
                    $motorizadoLocation->longitude,
                    $local->latitud,
                    $local->longitud
                );
                // Verificar si el motorizado está dentro del radio de 10 km
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
                    $cliente = Cliente::find($pedido->id_cliente);
                    $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
                    $coordenadasCliente = json_decode($clienteDireccion->coordenadas);
                    $names = array_map(function ($item) {
                        return $item['nombre'] . ' x ' . $item['cantidad'];
                    }, $pedidoDetalles->toArray());
                    $namesString = implode(', ', $names);
                    $pedido->tiempo_estimado = $tiempoEstimado;
                    $pedido->detalle = $namesString;
                    $pedido->local = $local->nombre_establecimiento;
                    $pedido->direccion_local = $local->direccion_completa;
                    $pedido->direccion_entrega = $clienteDireccion->direccion ?? '';
                    $pedido->cliente = $cliente->nombre . ' ' . $cliente->apellido;
                    $pedido->celular = $cliente->celular;
                    $pedido->lat_local = (float) $local->latitud;
                    $pedido->lon_local = (float) $local->longitud;
                    $pedido->latitud = $coordenadasCliente->coordinates[1];
                    $pedido->longitud = $coordenadasCliente->coordinates[0];
                    $pedido->estado = $estado->estado;
                    $pedido->nota = $pedido->nota ?? 'Sin nota';
                    $pedido->tiempo = $pedido->tiempo ?? 0;
                    $pedido->tipo_pago = $pedido->id_tipo_pago ? MedioPago::find($pedido->id_tipo_pago)->nombre : 'Efectivo';
                    $pedido->precio_delivery = $pedido->precio_delivery;
                    $pedido->total = ($pedido->subtotal + $pedido->precio_delivery) - $pedido->descuento;
                    $pedido->tipo_comprobante = $pedido->tipo_comprobante ?? 'Sin comprobante';
                }
            }
        }
        return $pedidos;
    }

    public function updateLocation(Request $request)
    {
        Location::create([
            'motorizado_id' => $request->motorizado_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);
        return response()->json(['message' => 'Location updated successfully'], 200);
    }

    public function updateToken(Request $request)
    {
        $reparto = RepartoRegistro::findOrFail($request->id_reparto);
        $reparto->token_fmc = $request->token_fcm;
        $reparto->save();

        return response()->json([
            'success' => true,
            'message' => 'Token actualizado correctamente',
            'data' => $reparto
        ]);
    }

    public function getPerfl($repartoId)
    {
        $reparto = RepartoRegistro::find($repartoId);
        if (!$reparto) {
            return response()->json(['error' => 'Repartidor no encontrado'], 404);
        }

        $user = User::find($reparto->user_id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $cuentaBancaria = CuentaBancariaReparto::where('reparto_registro_id', $repartoId)
            ->with(['banco', 'tipoCuenta'])
            ->first();



        return response()->json([
            'repartidor' => $reparto,
            'usuario' => $user,
            'cuentaBancaria' => $cuentaBancaria,
        ]);
    }

    public function condiciones($id)
    {
        // Verificar si el motorizado existe
        $motorizado = RepartoRegistro::find($id);
        if (!$motorizado) {
            return response()->json([
                'puede_trabajar' => false,
                'mensaje' => 'Motorizado no encontrado',
            ]);
        }

        // Buscar horario individual
        $horarioIndividual = HorarioGrupo::where('motorizado_individual_id', $id)
            ->where('tipo', 'individual')
            ->with('bloques')
            ->first();

        // Buscar horario grupal
        $horarioGrupal = HorarioAsignacion::where('motorizado_id', $id)
            ->with('grupo.bloques')
            ->first();

        // Determinar qué bloques usar
        $bloques = collect();
        $tipoHorario = 'ninguno';

        if ($horarioIndividual && $horarioIndividual->bloques->count() > 0) {
            $bloques = $horarioIndividual->bloques;
            $tipoHorario = 'individual';
        } elseif ($horarioGrupal && $horarioGrupal->grupo && $horarioGrupal->grupo->bloques->count() > 0) {
            $bloques = $horarioGrupal->grupo->bloques;
            $tipoHorario = 'grupal';
        }

        if ($bloques->isEmpty()) {
            return response()->json([
                'puede_trabajar' => false,
                'mensaje' => 'No hay horario asignado',
            ]);
        }

        // Verificar si tiene pedidos activos (estados 1-7, antes de entregado)
        // Solo considerar pedidos de HOY o de las últimas 24 horas
        $pedidosActivos = Pedido::where('id_motorizado', $id)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->whereHas('trackings', function ($query) {
                $query->whereIn('estado', [1, 2, 3, 4, 5, 6, 7])
                    ->whereRaw('id = (SELECT MAX(id) FROM pedido_trackings WHERE pedido_id = pedidos.id)');
            })
            ->count();

        $tienePedidoActivo = $pedidosActivos > 0;

        // DESHABILITADO: Verificación de límite de pedidos
        // Ya no se usa esta funcionalidad
        /*
        $cantidadPedidoPermitido = $motorizado->cantidad_pedidos_dias ?? 0;
        $cantidadPedidosRealizados = Pedido::where('id_motorizado', $id)
            ->whereDate('created_at', Carbon::today())
            ->whereHas('trackings', function ($query) {
                $query->latest()->where('estado', 8);
            })
            ->count();

        $limiteAlcanzado = $cantidadPedidoPermitido > 0 && $cantidadPedidosRealizados >= $cantidadPedidoPermitido;

        // Si alcanzó el límite pero tiene pedidos activos, puede seguir trabajando
        if ($limiteAlcanzado && !$tienePedidoActivo) {
            return response()->json([
                'puede_trabajar' => false,
                'fuera_de_horario' => false,
                'tiene_pedido_activo' => false,
                'pedidos_activos' => 0,
                'mensaje' => 'Ya alcanzó el límite de pedidos',
                'limite_restante' => 0
            ]);
        }
        */

        // Usar zona horaria de Perú
        $now = Carbon::now('America/Lima');
        $diaActual = strtolower($now->locale('es')->dayName);
        $diaActual = strtr($diaActual, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u'
        ]);
        $horaActual = $now->format('H:i');

        $dentroDeHorario = false;
        $bloqueActivo = null;
        $bloquesDelDia = [];

        // Recolectar TODOS los bloques del día actual
        foreach ($bloques as $bloque) {
            // Asegurarse de que dia_semana sea un array y normalizar los días
            $diasBloque = [];
            if (is_string($bloque->dia_semana)) {
                $diasBloque = json_decode($bloque->dia_semana, true);
            } elseif (is_array($bloque->dia_semana)) {
                $diasBloque = $bloque->dia_semana;
            }
            if (is_array($diasBloque)) {
                $diasBloque = array_map(function ($d) {
                    $d = strtolower($d);
                    $d = strtr($d, [
                        'á' => 'a',
                        'é' => 'e',
                        'í' => 'i',
                        'ó' => 'o',
                        'ú' => 'u'
                    ]);
                    return $d;
                }, $diasBloque);
            }

            // Verificar si el día actual está en los días del bloque
            if (is_array($diasBloque) && in_array($diaActual, $diasBloque)) {
                // Extraer solo la hora de los timestamps
                $horaInicio = Carbon::parse($bloque->hora_inicio)->format('H:i');
                $horaFin = Carbon::parse($bloque->hora_fin)->format('H:i');

                $bloquesDelDia[] = [
                    'tipo' => $bloque->tipo,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'descripcion' => $bloque->descripcion
                ];
            }
        }

        // Ordenar bloques por hora de inicio
        usort($bloquesDelDia, function ($a, $b) {
            return $a['hora_inicio'] <=> $b['hora_inicio'];
        });

        // Verificar si la hora actual está dentro de algún bloque, considerando los que cruzan medianoche
        foreach ($bloquesDelDia as $bloque) {
            $horaActualObj = Carbon::createFromFormat('H:i', $horaActual);
            $horaInicioObj = Carbon::createFromFormat('H:i', $bloque['hora_inicio']);
            $horaFinObj = Carbon::createFromFormat('H:i', $bloque['hora_fin']);

            if ($horaInicioObj <= $horaFinObj) {
                // Bloque normal, no cruza medianoche
                if ($horaActualObj >= $horaInicioObj && $horaActualObj <= $horaFinObj) {
                    $dentroDeHorario = true;
                    $bloqueActivo = $bloque;
                    break;
                }
            } else {
                // Bloque que cruza medianoche, ej: 20:00 a 02:00
                if ($horaActualObj >= $horaInicioObj || $horaActualObj <= $horaFinObj) {
                    $dentroDeHorario = true;
                    $bloqueActivo = $bloque;
                    break;
                }
            }
        }

        // Determinar si puede trabajar:
        // - Puede trabajar si está dentro de horario O tiene pedidos activos
        $puedeTrabajar = $dentroDeHorario || $tienePedidoActivo;

        // Determinar el mensaje apropiado
        $mensaje = '';
        if ($dentroDeHorario) {
            $mensaje = 'Puede trabajar';
        } elseif ($tienePedidoActivo) {
            $mensaje = 'Fuera de horario pero con pedido activo';
        } else {
            $mensaje = 'Se encuentra fuera del rango del horario';
        }

        // Calcular el horario completo del día
        $horaInicioDia = !empty($bloquesDelDia) ? min(array_column($bloquesDelDia, 'hora_inicio')) : null;
        $horaFinDia = !empty($bloquesDelDia) ? max(array_column($bloquesDelDia, 'hora_fin')) : null;

        // Contar bloques por tipo
        $bloquesTrabajo = array_filter($bloquesDelDia, function ($b) {
            return $b['tipo'] === 'trabajo';
        });
        $bloquesAlmuerzo = array_filter($bloquesDelDia, function ($b) {
            return $b['tipo'] === 'almuerzo';
        });

        return response()->json([
            'puede_trabajar' => $puedeTrabajar,
            'fuera_de_horario' => !$dentroDeHorario,
            'tiene_pedido_activo' => $tienePedidoActivo,
            'pedidos_activos' => $pedidosActivos,
            'mensaje' => $mensaje,
            'dia_actual' => $diaActual,
            'hora_actual' => $horaActual,
            // DESHABILITADO: Límite de pedidos ya no se usa
            // 'limite_restante' => max(0, $cantidadPedidoPermitido - $cantidadPedidosRealizados),
            // 'limite_alcanzado' => $limiteAlcanzado,
            'tipo_horario' => $tipoHorario,
            'bloque_activo' => $bloqueActivo,
            'bloques_del_dia' => $bloquesDelDia,
            'horario_completo' => [
                'hora_inicio' => $horaInicioDia,
                'hora_fin' => $horaFinDia,
                'total_horas' => $horaInicioDia && $horaFinDia ? $this->calcularHorasTotales($horaInicioDia, $horaFinDia) : 0
            ],
            'resumen_horario' => [
                'hora_inicio_dia' => $horaInicioDia,
                'hora_fin_dia' => $horaFinDia,
                'total_bloques_trabajo' => count($bloquesTrabajo),
                'total_bloques_almuerzo' => count($bloquesAlmuerzo),
                'tiene_almuerzo' => count($bloquesAlmuerzo) > 0
            ],
            'debug' => [
                'total_bloques' => $bloques->count(),
                'bloques_hoy' => count($bloquesDelDia),
                'servidor_hora_local' => $now->format('Y-m-d H:i:s'),
                'timezone' => $now->timezoneName,
                'dia_actual_raw' => $diaActual,
                'bloques_dias' => $bloques->map(function ($b) {
                    return [
                        'dias' => is_string($b->dia_semana) ? json_decode($b->dia_semana, true) : $b->dia_semana,
                        'hora_inicio' => $b->hora_inicio,
                        'hora_fin' => $b->hora_fin,
                        'tipo' => $b->tipo
                    ];
                })
            ]
        ]);
    }

    private function calcularHorasTotales($horaInicio, $horaFin)
    {
        $inicio = Carbon::createFromFormat('H:i', $horaInicio);
        $fin = Carbon::createFromFormat('H:i', $horaFin);

        // Si el fin es menor o igual que el inicio, asumimos que cruza la medianoche
        if ($fin <= $inicio) {
            $fin->addDay();
        }

        $minutos = $fin->diffInMinutes($inicio);
        return number_format($minutos / 60, 1);
    }

    public function actualizarEstado(Request $request)
    {
        $repartidor = RepartoRegistro::find($request->id);
        if ($repartidor) {
            $repartidor->activo = $request->activo;
            $repartidor->save();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    public function sendCode(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'email' => 'required|email',
            ]);

            // Generar nuevo código de verificación
            $newVerificationCode = random_int(100000, 999999);

            // Enviar el correo con el código de verificación
            Mail::to($request->email)->send(new SendCode($request->email, $newVerificationCode));

            $id = User::where('email', $request->email)->first()->id;

            // Retornar el código en la respuesta para ser usado en la aplicación
            return response()->json([
                'success' => true,
                'message' => 'Código de verificación enviado al correo electrónico',
                'status' => 200,
                'verification_code' => $newVerificationCode,
                'id' => $id
            ]);
        } catch (\Exception $e) {
            // Capturar cualquier error y devolver una respuesta de error
            return response()->json([
                'message' => 'Hubo un problema al reenviar el código de verificación. Por favor, intente nuevamente.',
                'error' => $e->getMessage() // Detalle del error para depuración
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $usuario = User::where('id', $request->id)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function updateInfo(Request $request, $id)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'celular' => 'required|string|max:30',
            'email' => 'required|email|max:255',
            'departamento' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }

        try {
            // Buscar el repartidor por ID
            $repartidor = RepartoRegistro::findOrFail($id);

            // Actualizar los datos
            $repartidor->celular = $request->celular;
            $repartidor->email = $request->email;
            $repartidor->departamento = $request->departamento;
            $repartidor->save();

            return response()->json([
                'mensaje' => 'Datos personales actualizados correctamente',
                'repartidor' => $repartidor,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar los datos personales',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function viajeActivo($idBiker)
    {
        $pedidos = Pedido::whereNotNull('id_local')
            ->where('id_motorizado', $idBiker)
            ->whereDate('created_at', Carbon::today())
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('pedido_id'))
                    ->from('pedido_trackings')
                    ->whereIn('estado', [2, 3, 4, 5, 6, 7])
                    ->whereIn(DB::raw('(pedido_id, created_at)'), function ($sub) {
                        $sub->select(DB::raw('pedido_id, MAX(created_at)'))
                            ->from('pedido_trackings')
                            ->groupBy('pedido_id');
                    });
            })
            ->get();
        $tiene_viaje_activo = $pedidos->isNotEmpty();
        $data = [];
        $pedido = [];
        if ($tiene_viaje_activo) {
            $pedido = $pedidos->first();
            $establecimiento = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
            $cliente = Cliente::find($pedido->id_cliente);
            $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
            $estado = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
            $pedido = [
                'id' => $pedido->id,
                'local' => $establecimiento->nombre_establecimiento,
                'establecimiento' => $establecimiento->nombre_establecimiento,
                'direccionLocal' => $establecimiento->direccion_completa,
                'direccionEntrega' => $clienteDireccion->direccion,
                'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
                'celular' => $cliente->celular,
                'tiempoEstimado' => $pedido->tiempo_estimado,
                'detalle' => $pedido->nota,
                'latLocal' => $establecimiento->latitud,
                'lonLocal' => $establecimiento->longitud,
                'latitud' => $pedido->latitud,
                'longitud' => $pedido->longitud,
                'productos' => $pedido->productos,
                'estado' => $estado->estado,
                'tiempo' => $pedido->tiempo,
                'nota' => $pedido->nota,
                'tipoPago' => $pedido->id_tipo_pago ? MedioPago::find($pedido->id_tipo_pago)->nombre : 'Efectivo',
                'precioDelivery' => $pedido->precio_delivery,
                'total' => ($pedido->subtotal + $pedido->precio_delivery) - $pedido->descuento,
                'tipoComprobante' => $pedido->tipo_comprobante ?? 'Sin comprobante',
            ];
        }
        $data = [
            "tiene_viaje_activo" => $tiene_viaje_activo,
            "pedido" => $pedido
        ];

        return response()->json($data);
    }

    public function viajesActivos($idBiker)
    {
        $pedidos = Pedido::whereNotNull('id_local')
            ->where('id_motorizado', $idBiker)
            ->whereDate('created_at', Carbon::today())
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('pedido_id'))
                    ->from('pedido_trackings')
                    ->whereIn('estado', [2, 3, 4, 5, 6, 7])
                    ->whereIn(DB::raw('(pedido_id, created_at)'), function ($sub) {
                        $sub->select(DB::raw('pedido_id, MAX(created_at)'))
                            ->from('pedido_trackings')
                            ->groupBy('pedido_id');
                    });
            })
            ->get();
        $tiene_viaje_activo = $pedidos->isNotEmpty();
        $data = [];
        if ($tiene_viaje_activo) {
            foreach ($pedidos as $pedido) {
                $establecimiento = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
                $cliente = Cliente::find($pedido->id_cliente);
                $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
                $estado = PedidoTracking::where('pedido_id', $pedido->id)->latest()->first();
                $productos = PedidoDetalle::where('pedido_id', $pedido->id)->get();
                $productosList = $productos->pluck('nombre');
                $productosListCantidad = PedidoDetalle::where('pedido_id', $pedido->id)
                    ->selectRaw("CONCAT(nombre, ' x ', cantidad) as descripcion")
                    ->pluck('descripcion');

                $data[] = [
                    'id' => $pedido->id,
                    'local' => $establecimiento->nombre_establecimiento,
                    'establecimiento' => $establecimiento->nombre_establecimiento,
                    'direccionLocal' => $establecimiento->direccion_completa,
                    'direccionEntrega' => $clienteDireccion->direccion,
                    'cliente' => $cliente->nombre . ' ' . $cliente->apellido,
                    'celular' => $cliente->celular,
                    'tiempoEstimado' => $pedido->tiempo_estimado,
                    'detalle' => $pedido->nota,
                    'latLocal' => $establecimiento->latitud,
                    'lonLocal' => $establecimiento->longitud,
                    'latitud' => $pedido->latitud,
                    'longitud' => $pedido->longitud,
                    'productos' => $pedido->productos,
                    'estado' => $estado->estado,
                    'tiempo' => $pedido->tiempo,
                    'nota' => $pedido->nota,
                    'tipoPago' => $pedido->id_tipo_pago ? MedioPago::find($pedido->id_tipo_pago)->nombre : 'Efectivo',
                    'precioDelivery' => $pedido->precio_delivery,
                    'total' => ($pedido->subtotal + $pedido->precio_delivery) - $pedido->descuento,
                    'tipoComprobante' => $pedido->tipo_comprobante ?? 'Sin comprobante',
                    'productosList' => $productosListCantidad,
                    'productos' => implode(', ', $productosList->toArray()),
                    'actualizado' => $pedido->updated_at,
                    'descuento' => $pedido->descuento
                ];
            }
        }

        return response()->json($data);
    }
}
