<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\LocalPrioridad;
use App\Models\PedidoTracking;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\CredencialesSocio;
use App\Mail\SendCode;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\MedioPago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\RepartoRegistro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SocioController extends Controller
{
    public function all()
    {
        try {
            $socios = BusinessRegistration::all();
            return response()->json($socios);
        } catch (\Exception $e) {
            Log::error('Error al obtener socios: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al obtener la lista de socios'], 500);
        }
    }

    public function changeState($id)
    {
        try {
            $socio = BusinessRegistration::findOrFail($id);
            $socio->estado = $socio->estado == 1 ? 0 : 1;
            $socio->save();
            return response()->json(['status' => 'success', 'message' => 'Estado del socio actualizado'], 200);
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del socio: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error al cambiar el estado del socio'], 500);
        }
    }

    public function getDetails($id)
    {
        try {
            $businessRegistration = BusinessRegistration::with([
                'negocio',
                'establecimiento',
                'datosClaveNegocio',
                'datosBancarios',
                'cuentaBancaria.banco',
                'cuentaBancaria.tipoCuenta',
                'documentosPdfExtranjero'
            ])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $businessRegistration->id,
                    'personal' => [
                        'name' => $businessRegistration->name,
                        'lastName' => $businessRegistration->lastName,
                        'email' => $businessRegistration->email,
                        'phone' => $businessRegistration->phone,
                        'businessType' => $businessRegistration->businessType,
                        'created_at' => $businessRegistration->created_at,
                        'posToDriver'=>$businessRegistration->posToDriver
                    ],
                    'business' => $businessRegistration->negocio ? [
                        'nombre' => $businessRegistration->negocio->nombre,
                        'total_sucursales' => $businessRegistration->negocio->total_sucursales,
                        'metodo_contacto' => $businessRegistration->negocio->metodo_contacto,
                        'telefono' => $businessRegistration->negocio->telefono
                    ] : null,
                    'businessData' => $businessRegistration->datosClaveNegocio ? [
                        'ruc' => $businessRegistration->datosClaveNegocio->ruc,
                        'razon_social' => $businessRegistration->datosClaveNegocio->razon_social
                    ] : null,
                    'establishment' => $businessRegistration->establecimiento ? [
                        'nombre_establecimiento' => $businessRegistration->establecimiento->nombre_establecimiento,
                        'direccion_completa' => $businessRegistration->establecimiento->direccion_completa,
                        'ciudad' => $businessRegistration->establecimiento->ciudad,
                        'codigo_postal' => $businessRegistration->establecimiento->codigo_postal
                    ] : null,
                    'bankData' => $businessRegistration->datosBancarios ? [
                        'titular_cuenta' => $businessRegistration->datosBancarios->titular_cuenta,
                        'numero_cuenta' => $businessRegistration->datosBancarios->numero_cuenta,
                        'nombre_banco' => $businessRegistration->datosBancarios->nombre_banco,
                        'tipo_cuenta' => $businessRegistration->datosBancarios->tipo_cuenta
                    ] : null,
                    'cuentaBancaria' => $businessRegistration->cuentaBancaria ? [
                        'titular_cuenta' => $businessRegistration->cuentaBancaria->titular_cuenta,
                        'dni' => $businessRegistration->cuentaBancaria->dni,
                        'banco' => $businessRegistration->cuentaBancaria->banco->nombre,
                        'tipo_cuenta' => $businessRegistration->cuentaBancaria->tipoCuenta->nombre,
                        'numero_cuenta' => $businessRegistration->cuentaBancaria->numero_cuenta,
                        'imagenes_cuenta' => json_decode($businessRegistration->cuentaBancaria->imagenes_cuenta)
                    ] : null,
                    'documentosPdfExtranjero' => $businessRegistration->documentosPdfExtranjero ? [
                        'antecedentes_penales_pdf' => $businessRegistration->documentosPdfExtranjero->antecedentes_penales_pdf,
                        'antecedentes_policiales_pdf' => $businessRegistration->documentosPdfExtranjero->antecedentes_policiales_pdf
                    ] : null


                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del socio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los detalles del socio'
            ], 500);
        }
    }

    public function aprobar($id)
    {
        DB::beginTransaction();
        try {
            // busca el registro de socio
            $socio = BusinessRegistration::findOrFail($id);

            // virifica si esta aprobado
            if ($socio->aprobado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El socio ya está aprobado'
                ], 400);
            }

            // Verificar si ya existe un usuario con el mismo correo
            $existingUser = User::where('email', $socio->email)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            // marca socio como aprobado
            $socio->aprobado = true;

            // Generar credenciales
            $username = $this->generateUniqueUsername($socio->name, $socio->lastName);
            $password = Str::random(10);

            // Obtener el rol de negocio
            $rolNegocio = Role::where('name', 'negocio')->firstOrFail();

            // Crear un nuevo usuario
            $user = new User();
            $user->usuario = $username;
            $user->name = $socio->name . ' ' . $socio->lastName;
            $user->email = $socio->email;
            $user->password = bcrypt($password);
            $user->role_id = $rolNegocio->id;
            $user->save();

            // Asociar el usuario al socio
            $socio->user_id = $user->id;
            $socio->save();

            // Enviar correo con las credenciales
            Mail::to($socio->email)->send(new CredencialesSocio(
                $username,
                $password,
                $socio->id
            ));

            DB::commit(); // Commit de la transacción de la base de datos

            return response()->json([
                'status' => 'success',
                'message' => 'Socio aprobado exitosamente y credenciales enviadas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar socio: ' . $e->getMessage());

            // Verificar si es un error de duplicación de correo
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email_unique') !== false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el socio: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateUniqueUsername($name, $lastName)
    {
        // Dividir nombres y apellidos
        $nombresArray = explode(' ', trim($name));
        $apellidosArray = explode(' ', trim($lastName));

        // Obtener la primera letra del primer nombre en mayúscula
        $primeraNombre = ucfirst(substr($nombresArray[0], 0, 1));

        // // Obtener el segundo nombre si existe, si no, usar el primer nombre
        // $segundoNombre = isset($nombresArray[1]) ? strtolower($nombresArray[1]) : strtolower($nombresArray[0]);

        // // Obtener la primera letra del primer apellido en mayúscula
        // $primeraApellido = isset($apellidosArray[0]) ? ucfirst(substr($apellidosArray[0], 0, 1)) : '';

        // cambiar a obtener el primer apellido completo en minusculas
        $primeraApellido = isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '';

        // // Obtener el segundo apellido si existe, si no, usar el primer apellido
        // $segundoApellido = isset($apellidosArray[1]) ? strtolower($apellidosArray[1]) : 
        //                    (isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '');

        // Construir el nombre de usuario base (primera letra del nombre + primer apellido)
        // $baseUsername = $primeraNombre . $segundoNombre . $primeraApellido . $segundoApellido;
        $baseUsername = $primeraNombre . $primeraApellido;
        $username = $baseUsername;
        $counter = 1;

        // Verificar si el usuario ya existe y agregar número si es necesario
        while (User::where('usuario', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }


    public function login(Request $request)
    {
        // Buscar el reparto_registro
        $user = User::where('usuario', $request->usuario)
            ->where('estado', 1) // Estado debe ser 1
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Credenciales incorrectas'], 401);
        }

        // Si todo está bien, devolver el usuario y token
        $socio = BusinessRegistration::where('user_id', $user->id)
            ->where('estado', 1) // Estado debe ser 1
            ->where('aprobado', 1) // Aprobación debe ser 1
            ->first();


        // Asumiendo que usas Sanctum o Passport para autenticación con tokens
        return response()->json([
            'status' => 'success',
            'message' => 'Inicio de sesión exitoso',
            'user' => $user,
            'socio' => $socio,
            'token' => $user->createToken('your-app-name')->plainTextToken,
        ]);
    }

    public function getPedidos($id)
    {
        $tienda = BusinessRegistration::find($id);
        if (!$tienda->activo) {
            return [];
        }
        $pedidos = Pedido::with([
            'trackings' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])
            ->where('id_local', $id)
            ->whereDate('created_at', Carbon::today())
            ->get();
        $local = Establecimiento::where('business_registration_id', $id)->first();

        foreach ($pedidos as $pedido) {
            $pedidoDetalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
            $cliente = Cliente::find($pedido->id_cliente);
            $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();

            $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado)->only(['nombres', 'apellidos', 'celular']) : null;
            if ($motorizado) {
                $pedido->motorizado = $motorizado['nombres'] . ' ' . $motorizado['apellidos'] ?? '';
                $pedido->celular_motorizado = $motorizado['celular'] ?? '';
            } else {
                $pedido->motorizado = '';
                $pedido->celular_motorizado = '';
            }

            $names = array_map(function ($item) {
                return $item['nombre'];
            }, $pedidoDetalles->toArray());
            $namesString = implode(', ', $names);

            // Obtener el último estado del tracking
            $ultimoTracking = $pedido->trackings->first();
            $pedido->ultimo_estado_tracking = $ultimoTracking ? $ultimoTracking->estado : 'Sin seguimiento';
            $pedido->estado = $ultimoTracking ? estadoPedido($ultimoTracking->estado) : 'Sin seguimiento';

            // Agregar información adicional
            $pedido->detalle = $namesString;
            $pedido->detalleArray = $pedidoDetalles;
            $pedido->local = $local->nombre_establecimiento;
            $pedido->direccion_local = $local->direccion_completa;
            $pedido->direccion_entrega = $clienteDireccion->direccion ?? '';
            $pedido->cliente = $cliente->nombre . ' ' . $cliente->apellido;
            $pedido->celular = $cliente->celular;
            $pedido->lat_local = $local->latitud;
            $pedido->lon_local = $local->longitud;
            $pedido->tiempo = $pedido->tiempo ?? 0;
            $pedido->nota = $pedido->nota ?? 'Sin nota';
            $pedido->tipo_pago = $pedido->id_tipo_pago ? MedioPago::find($pedido->id_tipo_pago)->nombre : 'Efectivo';
            $pedido->requiere_confirmacion_local = $pedido->requiere_confirmacion_local == 1 ? true : false;
        }

        // Ordenar los pedidos por el último estado del tracking de manera descendente
        $pedidos = $pedidos->sortByDesc('ultimo_estado_tracking')->values();

        return response()->json($pedidos);
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            // primero Buscar el socio
            $socio = BusinessRegistration::findOrFail($id);

            // Si el socio tiene un usuario asociado, eliminarlo también
            if ($socio->user_id) {
                $user = User::find($socio->user_id);
                if ($user) {
                    $user->delete();
                }
            }

            // Eliminar registros relacionados 
            if ($socio->negocio) {
                $socio->negocio->delete();
            }

            if ($socio->establecimiento) {
                $socio->establecimiento->delete();
            }

            if ($socio->datosClaveNegocio) {
                $socio->datosClaveNegocio->delete();
            }

            if ($socio->datosBancarios) {
                $socio->datosBancarios->delete();
            }

            if ($socio->cuentaBancaria) {
                $socio->cuentaBancaria->delete();
            }

            if ($socio->revisarDatos) {
                $socio->revisarDatos->delete();
            }

            if ($socio->documentosPdfExtranjero) {
                $socio->documentosPdfExtranjero->delete();
            }

            // if ($socio->perfil) {
            //     $socio->perfil->delete();
            // }

            // Finalmente eliminar el socio
            $socio->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Socio eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar socio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el socio: ' . $e->getMessage()
            ], 500);
        }
    }
    public function updateEstadoPedido(Request $request, $id)
    {
        try {
            $pedido = Pedido::findOrFail($id);
            $estado = $request->estado;

            // Validar que el estado sea válido
            if (!in_array($estado, [1, 2, 3, 4, 5, 6, 7])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Estado no válido'
                ], 400);
            }

            // Crear un nuevo tracking
            $tracking = new PedidoTracking();
            $tracking->pedido_id = $id;
            $tracking->estado = $estado;
            $tracking->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado del pedido actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del pedido: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estado del pedido: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * Actualiza la prioridad de un local específico
     */
    public function actualizarPrioridadLocal(Request $request, $id)
    {
        try {
            // Validamos que la prioridad sea un número positivo (sin límite superior)
            $request->validate([
                'prioridad' => 'required|numeric|min:0',
            ]);

            // Verificamos que el local exista en la base de datos
            $local = Establecimiento::findOrFail($id);

            // Actualizamos o creamos el registro de prioridad
            LocalPrioridad::updateOrCreate(
                // Condición para buscar: establecimiento_id debe coincidir
                ['establecimiento_id' => $id],
                // Datos a actualizar o insertar
                [
                    'prioridad' => $request->prioridad,
                    'fecha_actualizacion' => now()
                ]
            );

            // Retornamos una respuesta JSON exitosa
            return response()->json([
                'status' => 'success',
                'message' => 'Prioridad actualizada correctamente'
            ]);
        } catch (\Exception $e) {
            // Registramos el error en el log para debugging
            Log::error('Error al actualizar prioridad: ' . $e->getMessage());

            // Retornamos una respuesta de error con código 500
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la prioridad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los locales ordenados por prioridad
     */
    public function getLocalesPorPrioridad()
    {
        try {
            // Obtener todos los establecimientos con sus datos de negocio, perfil y prioridad
            $locales = Establecimiento::with([
                'businessRegistration',
                'businessRegistration.perfil'
                // 'rating'
            ])
                ->join('business_registrations', 'establecimientos.business_registration_id', '=', 'business_registrations.id')
                // Hacemos un left join con la tabla de prioridades para incluir todos los locales
                ->leftJoin('local_priorities', 'establecimientos.id', '=', 'local_priorities.establecimiento_id')
                ->where('business_registrations.estado', 1)
                ->where('business_registrations.aprobado', 1)
                // Ordenamos por prioridad (de mayor a menor) y luego por nombre
                // Mayor número = mayor prioridad
                ->orderBy('local_priorities.prioridad', 'desc')
                ->orderBy('establecimientos.nombre_establecimiento', 'asc')
                // Seleccionamos todas las columnas de la tabla establecimientos y la prioridad
                ->select('establecimientos.*', 'local_priorities.prioridad')
                ->get();

            // Transformamos la colección de locales para incluir la puntuación y prioridad
            $localesFormateados = $locales->map(function ($local) {
                // Obtenemos la puntuación del local usando la relación
                // $puntuacion = $local->rating ? $local->rating->puntuacion : 0;

                // Verificamos si businessRegistration y perfil existen
                $logo = null;
                if (
                    $local->businessRegistration &&
                    $local->businessRegistration->perfil &&
                    $local->businessRegistration->perfil->ruta_logo
                ) {
                    $logo = '/' . ltrim($local->businessRegistration->perfil->ruta_logo, '/');
                }

                // Retornamos un array con los datos formateados del local
                return [
                    'id' => $local->id,
                    'nombre' => $local->nombre_establecimiento,
                    'direccion' => $local->direccion_completa,
                    'ciudad' => $local->ciudad,
                    'business_id' => $local->business_registration_id,
                    'empresa' => $local->businessRegistration->name . ' ' . $local->businessRegistration->lastName,
                    'logo' => $logo,
                    // 'puntuacion' => $puntuacion,
                    'prioridad' => $local->prioridad ?? 0, // Si no tiene prioridad, asignamos 0
                    'latitud' => $local->latitud,
                    'longitud' => $local->longitud
                ];
            });

            // Retornamos una respuesta JSON exitosa con los datos
            return response()->json([
                'status' => 'success',
                'data' => $localesFormateados
            ]);
        } catch (\Exception $e) {
            // Registramos el error en el log para debugging
            Log::error('Error al obtener locales por prioridad: ' . $e->getMessage());

            // Retornamos una respuesta de error con código 500
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la lista de locales por prioridad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas sobre las prioridades de los locales
     */
    public function getEstadisticasPrioridad()
    {
        try {
            // Obtener el total de locales
            $totalLocales = Establecimiento::join('business_registrations', 'establecimientos.business_registration_id', '=', 'business_registrations.id')
                ->where('business_registrations.estado', 1)
                ->where('business_registrations.aprobado', 1)
                ->count();

            // Si no hay locales, devolver estadísticas en cero
            if ($totalLocales === 0) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'totalLocales' => 0,
                        'highPriorityLocales' => 0,
                        'mediumPriorityLocales' => 0,
                        'lowPriorityLocales' => 0
                    ]
                ]);
            }

            // Obtener todos los locales con prioridad
            $localesConPrioridad = LocalPrioridad::all();

            // Calcular los umbrales para las categorías basados en el total de locales
            $lowThreshold = ceil($totalLocales * 0.33); // Primer tercio
            $mediumThreshold = ceil($totalLocales * 0.67); // Segundo tercio

            // Ordenar las prioridades de menor a mayor
            $prioridades = $localesConPrioridad->pluck('prioridad')->sort()->values();

            // Contar locales por categoría de prioridad
            $bajaPrioridad = 0;
            $mediaPrioridad = 0;
            $altaPrioridad = 0;

            foreach ($localesConPrioridad as $local) {
                // Encontrar la posición de la prioridad en la lista ordenada
                $posicion = $prioridades->search($local->prioridad) + 1;

                if ($posicion <= $lowThreshold) {
                    $bajaPrioridad++;
                } elseif ($posicion <= $mediumThreshold) {
                    $mediaPrioridad++;
                } else {
                    $altaPrioridad++;
                }
            }

            // Retornamos una respuesta JSON exitosa con los datos
            return response()->json([
                'status' => 'success',
                'data' => [
                    'totalLocales' => $totalLocales,
                    'highPriorityLocales' => $altaPrioridad,
                    'mediumPriorityLocales' => $mediaPrioridad,
                    'lowPriorityLocales' => $bajaPrioridad
                ]
            ]);
        } catch (\Exception $e) {
            // Registramos el error en el log para debugging
            Log::error('Error al obtener estadísticas de prioridad: ' . $e->getMessage());

            // Retornamos una respuesta de error con código 500
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener las estadísticas de prioridad: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Obtiene todos los locales disponibles con su información básica y logo
     */
    public function getAllLocales()
    {
        try {
            // Obtener todos los establecimientos con sus datos de negocio y perfil
            $locales = Establecimiento::with([
                'businessRegistration',
                'businessRegistration.perfil',
            ])
                ->join('business_registrations', 'establecimientos.business_registration_id', '=', 'business_registrations.id')
                ->where('business_registrations.estado', 1)
                ->where('business_registrations.aprobado', 1)
                ->orderBy('establecimientos.nombre_establecimiento', 'asc')
                ->select('establecimientos.*')
                ->get();

            // Transformamos la colección de locales para incluir la información necesaria
            $localesFormateados = $locales->map(function ($local) {
                // Verificamos si businessRegistration y perfil existen
                $logo = null;
                $empresa = '';

                if ($local->businessRegistration) {
                    $empresa = $local->businessRegistration->name . ' ' . $local->businessRegistration->lastName;

                    if (
                        $local->businessRegistration->perfil &&
                        $local->businessRegistration->perfil->ruta_logo
                    ) {
                        $logo = '/' . ltrim($local->businessRegistration->perfil->ruta_logo, '/');
                    }
                }

                // Obtener la puntuación del local calculando el promedio de las calificaciones
                // Usamos una subconsulta para obtener el promedio de restaurant_rating
                $puntuacion = DB::table('ratings')
                    ->join('pedidos', 'ratings.id_pedido', '=', 'pedidos.id')
                    ->where('pedidos.id_local', $local->business_registration_id)
                    ->whereNotNull('ratings.restaurant_rating')
                    ->avg('ratings.restaurant_rating') ?? 0;

                // Obtener el conteo de pedidos
                $pedidoCount = Pedido::where('id_local', $local->business_registration_id)->count();

                // Retornamos un array con los datos formateados del local
                return [
                    'id' => $local->id,
                    'nombre' => $local->nombre_establecimiento,
                    'direccion' => $local->direccion_completa,
                    'ciudad' => $local->ciudad,
                    'business_id' => $local->business_registration_id,
                    'empresa' => $empresa,
                    'logo' => $logo,
                    'puntuacion' => $puntuacion,
                    'pedidoCount' => $pedidoCount,
                    'latitud' => $local->latitud,
                    'longitud' => $local->longitud
                ];
            });

            // Retornamos una respuesta JSON exitosa con los datos
            return response()->json([
                'status' => 'success',
                'data' => $localesFormateados
            ]);
        } catch (\Exception $e) {
            // Registramos el error en el log para debugging
            Log::error('Error al obtener locales: ' . $e->getMessage());

            // Retornamos una respuesta de error con código 500
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la lista de locales: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarEstado(Request $request)
    {
        $local = BusinessRegistration::find($request->id);
        if ($local) {
            $local->activo = $request->activo;
            $local->save();
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

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo es incorrecto.',
                    'status' => 500,
                ]);
            }

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);

            // Enviar el correo con el código de verificación
            Mail::to($request->email)->send(new SendCode($request->email, $newVerificationCode));


            // Retornar el código en la respuesta para ser usado en la aplicación
            return response()->json([
                'success' => true,
                'message' => 'Código de verificación enviado al correo electrónico',
                'status' => 200,
                'verification_code' => $newVerificationCode,
                'id' => $user->id
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

    public function getPedido($id)
    {
        $pedido = Pedido::with([
            'trackings' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])
            ->where('id', $id)
            ->whereDate('created_at', Carbon::today())
            ->first();
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();

        $pedidoDetalles = PedidoDetalle::where('pedido_id', $id)->get();
        $cliente = Cliente::find($pedido->id_cliente);
        $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();

        $motorizado = $pedido->id_motorizado ? RepartoRegistro::find($pedido->id_motorizado)->only(['nombres', 'apellidos', 'celular']) : null;
        if ($motorizado) {
            $pedido->motorizado = $motorizado['nombres'] . ' ' . $motorizado['apellidos'] ?? '';
            $pedido->celular_motorizado = $motorizado['celular'] ?? '';
        } else {
            $pedido->motorizado = '';
            $pedido->celular_motorizado = '';
        }

        $names = array_map(function ($item) {
            return $item['nombre'];
        }, $pedidoDetalles->toArray());
        $namesString = implode(', ', $names);

        // Obtener el último estado del tracking
        $ultimoTracking = $pedido->trackings->first();
        $pedido->ultimo_estado_tracking = $ultimoTracking ? $ultimoTracking->estado : 'Sin seguimiento';
        $pedido->estado = $ultimoTracking ? estadoPedido($ultimoTracking->estado) : 'Sin seguimiento';

        // Agregar información adicional
        $pedido->detalle = $namesString;
        $pedido->detalleArray = $pedidoDetalles;
        $pedido->local = $local->nombre_establecimiento;
        $pedido->direccion_local = $local->direccion_completa;
        $pedido->direccion_entrega = $clienteDireccion->direccion ?? '';
        $pedido->cliente = $cliente->nombre . ' ' . $cliente->apellido;
        $pedido->celular = $cliente->celular;
        $pedido->lat_local = $local->latitud;
        $pedido->lon_local = $local->longitud;
        $pedido->tiempo = $pedido->tiempo ?? 0;
        $pedido->nota = $pedido->nota ?? 'Sin nota';
        $pedido->tipo_pago = $pedido->id_tipo_pago ? MedioPago::find($pedido->id_tipo_pago)->nombre : 'Efectivo';

        return response()->json([$pedido]);
    }

    public function updateToken(Request $request)
    {
        $reparto = BusinessRegistration::findOrFail($request->id_reparto);
        $reparto->token_fmc = $request->token_fcm;
        $reparto->save();

        return response()->json([
            'success' => true,
            'message' => 'Token actualizado correctamente',
            'data' => $reparto
        ]);
    }
}
