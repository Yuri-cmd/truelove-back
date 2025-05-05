<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\CredencialesSocio;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoTracking;
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
                        'created_at' => $businessRegistration->created_at
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
        
        // Obtener el segundo nombre si existe, si no, usar el primer nombre
        $segundoNombre = isset($nombresArray[1]) ? strtolower($nombresArray[1]) : strtolower($nombresArray[0]);
        
        // Obtener la primera letra del primer apellido en mayúscula
        $primeraApellido = isset($apellidosArray[0]) ? ucfirst(substr($apellidosArray[0], 0, 1)) : '';
        
        // Obtener el segundo apellido si existe, si no, usar el primer apellido
        $segundoApellido = isset($apellidosArray[1]) ? strtolower($apellidosArray[1]) : 
                           (isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '');
        
        // Construir el nombre de usuario base
        $baseUsername = $primeraNombre . $segundoNombre . $primeraApellido . $segundoApellido;
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
        $pedidos = Pedido::with(['trackings' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])
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
                $pedido->celular_motorizado =  '';
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
            
            if ($socio->perfil) {
                $socio->perfil->delete();
            }
            
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
    

}
