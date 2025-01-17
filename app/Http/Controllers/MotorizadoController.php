<?php

namespace App\Http\Controllers;

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
                'registroVehiculo'
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
                        'documento_imagen_reverso' => $motorizado->documento_imagen_reverso
                    ],
                    'datosPersonales' => $motorizado->datosPersonales ? [
                        'fecha_nacimiento' => $motorizado->datosPersonales->fecha_nacimiento,
                        'genero' => $motorizado->datosPersonales->genero,
                        'url_selfie' => $motorizado->datosPersonales->url_selfie,
                        'ciudad' => $motorizado->datosPersonales->ciudad->nombre,
                        'distrito' => $motorizado->datosPersonales->distrito->nombre
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
                    'aprobado' => $motorizado->aprobado
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

    public function aprobar($id)
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

            $motorizado->aprobado = true;
            
            // Generar credenciales únicas
            $username = $this->generateUniqueUsername($motorizado->nombres, $motorizado->apellidos);
            $password = Str::random(10);
            
            // Obtener el rol de motorizado
            $rolMotorizado = Role::where('name', 'motorizado')->firstOrFail();
            
            // Crear un nuevo usuario
            $user = new User();
            $user->usuario = $username;
            $user->name = $motorizado->nombres . ' ' . $motorizado->apellidos;
            $user->email = $motorizado->email;
            $user->password = bcrypt($password);
            $user->role_id = $rolMotorizado->id;
            $user->save();

            // Asociar el usuario al motorizado
            $motorizado->user_id = $user->id;
            $motorizado->save();

            // Enviar correo con las credenciales
            Mail::to($motorizado->email)->send(new CredencialesMotorizado($username, $password));
    
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado aprobado exitosamente y credenciales enviadas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el motorizado: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateUniqueUsername($nombres, $apellidos)
    {
        $baseUsername = strtolower(str_replace(' ', '', $nombres . $apellidos));
        $username = $baseUsername;
        $counter = 1;

        while (User::where('usuario', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
}