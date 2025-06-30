<?php

namespace App\Http\Controllers;

use App\Models\EntregaCalendario;
use App\Models\RepartoRegistro;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\CredencialesMotorizado;
use Illuminate\Support\Facades\DB;

class MotorizadoController extends Controller
{
    public function all()
    {
        return response()->json(RepartoRegistro::all());
    }

    public function changeState($id)
    {
        $motorizado = RepartoRegistro::find($id);
        $motorizado->estado = !$motorizado->estado;
        $motorizado->save();
        return response()->json(['message' => 'Estado actualizado'], 200);
    }

    public function getDetails($id)
    {
        try {
            $motorizado = RepartoRegistro::with([
                'datosPersonales',
                'datosBancarios',
                'registroVehiculo',
                'entregaCalendario'
            ])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $motorizado->id,
                    'personal' => [
                        'name' => $motorizado->nombres,
                        'lastName' => $motorizado->apellidos,
                        'email' => $motorizado->email,
                        'phone' => $motorizado->celular,
                        'tipo_documento' => $motorizado->tipo_documento,
                        'nro_documento' => $motorizado->nro_documento,
                        'created_at' => $motorizado->created_at,
                        'documento_imagen_frente' => $motorizado->documento_imagen_frente,
                        'documento_imagen_reverso' => $motorizado->documento_imagen_reverso,
                        'documentos_adicionales' => $motorizado->documentos_adicionales,
                        'vehiculo' => $motorizado->vehiculo,
                        'departamento' => $motorizado->departamento,
                    ],
                    'datosPersonales' => $motorizado->datosPersonales ? [
                        'fecha_nacimiento' => $motorizado->datosPersonales->fecha_nacimiento,
                        'genero' => $motorizado->datosPersonales->genero,
                        'url_selfie' => $motorizado->datosPersonales->url_selfie,
                        'departamento' => $motorizado->datosPersonales->getDepartamentoAttribute(),
                        'distrito' => $motorizado->datosPersonales->getDistritoAttribute(),
                        // Mantener ciudad para compatibilidad con el frontend existente
                        'provincia' => $motorizado->datosPersonales->getProvinciaAttribute()
                    ] : null,
                    'datosBancarios' => $motorizado->datosBancarios ? [
                        'titular' => $motorizado->datosBancarios->titular,
                        'dni' => $motorizado->datosBancarios->dni,
                        'banco' => $motorizado->datosBancarios->banco->nombre,
                        'tipo_cuenta' => $motorizado->datosBancarios->tipoCuenta->nombre,
                        'numero_cuenta' => $motorizado->datosBancarios->numero_cuenta,
                        'imagen_cuenta' => $motorizado->datosBancarios->url_imagen_cuenta
                    ] : null,
                    'registroVehiculo' => $motorizado->registroVehiculo ? [
                        'placa' => $motorizado->registroVehiculo->placa,
                        'licencia_conducir' => $motorizado->registroVehiculo->licencia_conducir,
                        'seguro' => $motorizado->registroVehiculo->seguro,
                        'tarjeta_propiedad' => $motorizado->registroVehiculo->tarjeta_propiedad,
                        'imagen_placa' => $motorizado->registroVehiculo->imagen_placa,
                        'imagen_licencia' => $motorizado->registroVehiculo->imagen_licencia,
                        'imagen_seguro' => $motorizado->registroVehiculo->imagen_seguro,
                        'imagen_tarjeta_propiedad' => $motorizado->registroVehiculo->imagen_tarjeta_propiedad
                    ] : null,
                    'entregaCalendario' => $motorizado->entregaCalendario,
                    'aprobado' => $motorizado->aprobado,
                    'cantidad_pedidos_dias' => $motorizado->cantidad_pedidos_dias

                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles del motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los detalles del motorizado'
            ], 500);
        }
    }

    public function aprobar(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $motorizado = RepartoRegistro::findOrFail($id);

            if ($motorizado->aprobado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El motorizado ya está aprobado'
                ], 400);
            }

            // Verificar si ya existe un usuario con el mismo correo
            $existingUser = User::where('email', $motorizado->email)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            $motorizado->aprobado = true;
            // Guardar cantidad de pedidos si se proporciona
            if ($request->has('cantidad_pedidos_dias')) {
                $motorizado->cantidad_pedidos_dias = $request->cantidad_pedidos_dias;
            }

            // Generar credenciales únicas
            // $username = $this->generateUniqueUsername($motorizado->nombres, $motorizado->apellidos);
            // $password = Str::random(10);

            $password = Str::random(10);

            // Obtener el rol de motorizado
            $rolMotorizado = Role::where('name', 'motorizado')->firstOrFail();

            // Crear un nuevo usuario
            $user = new User();
            $user->usuario = $motorizado->email; // Usar el email como usuario
            $user->name = $motorizado->nombres . ' ' . $motorizado->apellidos;
            $user->email = $motorizado->email;
            $user->password = bcrypt($password);
            $user->role_id = $rolMotorizado->id;
            $user->save();

            // Asociar el usuario al motorizado
            $motorizado->user_id = $user->id;
            $motorizado->save();

            // Enviar correo con las credenciales
          Mail::to($motorizado->email)->send(new CredencialesMotorizado($motorizado, $password));
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado aprobado exitosamente y credenciales enviadas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar motorizado: ' . $e->getMessage());

            // Verificar si es un error de duplicación de correo
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'email_unique') !== false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El correo electrónico ya está registrado en el sistema'
                ], 400);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el motorizado: ' . $e->getMessage()
            ], 500);
        }
    }
    // este es para motorizado
    private function generateUniqueUsername($nombres, $apellidos)
    {
        // Dividir nombres y apellidos
        $nombresArray = explode(' ', trim($nombres));
        $apellidosArray = explode(' ', trim($apellidos));

        // Obtener la primera letra del primer nombre en mayúscula
        $primeraNombre = ucfirst(substr($nombresArray[0], 0, 1));

        // // Obtener el segundo nombre si existe, si no, usar el primer nombre
        // $segundoNombre = isset($nombresArray[1]) ? strtolower($nombresArray[1]) : strtolower($nombresArray[0]);

        // Obtener la primera letra del primer apellido en mayúscula
        // $primeraApellido = isset($apellidosArray[0]) ? ucfirst(substr($apellidosArray[0], 0, 1)) : '';
        $primeraApellido = isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '';
        // // Obtener el segundo apellido si existe, si no, usar el primer apellido
        // $segundoApellido = isset($apellidosArray[1]) ? strtolower($apellidosArray[1]) : 
        //                    (isset($apellidosArray[0]) ? strtolower($apellidosArray[0]) : '');

        // Construir el nombre de usuario base
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
    public function delete($id)
    {
        DB::beginTransaction();
        try {
            // Buscar el motorizado
            $motorizado = RepartoRegistro::findOrFail($id);

            // Si el motorizado tiene un usuario asociado, eliminarlo también
            if ($motorizado->user_id) {
                $user = User::find($motorizado->user_id);
                if ($user) {
                    $user->delete();
                }
            }

            // Eliminar registros relacionados
            if ($motorizado->datosPersonales) {
                $motorizado->datosPersonales->delete();
            }

            if ($motorizado->datosBancarios) {
                $motorizado->datosBancarios->delete();
            }

            if ($motorizado->registroVehiculo) {
                $motorizado->registroVehiculo->delete();
            }

            if ($motorizado->entregaCalendario) {
                foreach ($motorizado->entregaCalendario as $entrega) {
                    $entrega->delete();
                }
            }

            // Finalmente eliminar el motorizado
            $motorizado->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el motorizado: ' . $e->getMessage()
            ], 500);
        }
    }
    public function actualizarCantidadPedidos(Request $request, $id)
    {
        try {
            $motorizado = RepartoRegistro::findOrFail($id);
            $motorizado->cantidad_pedidos_dias = $request->cantidad_pedidos_dias;
            $motorizado->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Cantidad de pedidos actualizada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar cantidad de pedidos: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar la cantidad de pedidos: ' . $e->getMessage()
            ], 500);
        }
    }
    // Agregar este método al MotorizadoController
    public function getEntregaCalendarios($motorizadoId)
    {
        try {
            $entregas = EntregaCalendario::where('reparto_registro_id', $motorizadoId)
                ->orderBy('fecha', 'desc')
                ->orderBy('hora', 'desc')
                ->get();

            return response()->json($entregas);
        } catch (\Exception $e) {
            Log::error('Error al obtener calendario de entregas: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener el calendario de entregas'
            ], 500);
        }
    }


}