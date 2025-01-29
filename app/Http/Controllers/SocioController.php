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
use Illuminate\Support\Facades\DB;

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
                'cuentaBancaria.tipoCuenta'
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
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el socio: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateUniqueUsername($name, $lastName)
    {
        $baseUsername = strtolower(str_replace(' ', '', $name . $lastName));
        $username = $baseUsername;
        $counter = 1;

        while (User::where('usuario', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
}