<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\Negocio;
use App\Models\Establecimiento;
use App\Models\DatosClaveNegocio;
use App\Models\DatosBancarios;
use App\Models\SociosCuentaBancaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SocioController extends Controller
{
    public function all()
    {
        return response()->json(BusinessRegistration::all());
    }

    public function changeState($id)
    {
        $socio = BusinessRegistration::find($id);
        $socio->estado =  $socio->estado == 1 ? 0 : 1;
        $socio->save();
        return response()->json(201);
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
        try {
            $socio = BusinessRegistration::findOrFail($id);
            
            // Verificar si ya está aprobado
            if ($socio->aprobado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El socio ya está aprobado'
                ], 400);
            }
    
            $socio->aprobado = true;
            $socio->save();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Socio aprobado exitosamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Socio no encontrado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Socio no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al aprobar socio: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el socio: ' . $e->getMessage()
            ], 500);
        }
    }
    
    
}

