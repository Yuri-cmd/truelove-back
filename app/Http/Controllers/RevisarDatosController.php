<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\Establecimiento;
use App\Models\DatosClaveNegocio;
use App\Models\DatosBancarios;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class RevisarDatosController extends Controller
{
    public function obtenerDatosRevision(Request $request)
    {
        try {
            $token = $request->bearerToken();
            
            if ($token) {
                // Decodificar el token para obtener el registration_id
                $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
                $registrationId = $decoded->registration_id;
                
                $businessRegistration = BusinessRegistration::find($registrationId);
                
                if (!$businessRegistration) {
                    return response()->json([
                        'error' => 'Registro no encontrado'
                    ], 404);
                }

                $negocio = Negocio::with(['tipoNegocio', 'categoria'])
                    ->where('business_registration_id', $registrationId)
                    ->first();
                $establecimiento = Establecimiento::where('business_registration_id', $registrationId)
                    ->first();
                $datosClaveNegocio = DatosClaveNegocio::where('business_registration_id', $registrationId)
                    ->first();
                $datosBancarios = DatosBancarios::where('business_registration_id', $registrationId)
                    ->first();

                if (!$negocio || !$establecimiento || !$datosClaveNegocio || !$datosBancarios) {
                    return response()->json([
                        'error' => 'Datos incompletos',
                        'detalles' => [
                            'negocio' => $negocio ? 'encontrado' : 'no encontrado',
                            'establecimiento' => $establecimiento ? 'encontrado' : 'no encontrado',
                            'datosClaveNegocio' => $datosClaveNegocio ? 'encontrado' : 'no encontrado',
                            'datosBancarios' => $datosBancarios ? 'encontrado' : 'no encontrado'
                        ]
                    ], 404);
                }

                return response()->json([
                    'datos_negocio' => [
                        'nombre' => $negocio->nombre,
                        'tipo' => $negocio->tipoNegocio->nombre,
                        'categoria' => $negocio->categoria->nombre,
                        'total_sucursales' => $negocio->total_sucursales,
                        'metodo_contacto' => $negocio->metodo_contacto,
                        'telefono' => $negocio->telefono,
                        'es_local_calle' => $negocio->es_local_calle
                    ],
                    'direccion_negocio' => [
                        'nombre_establecimiento' => $establecimiento->nombre_establecimiento,
                        'calle' => $establecimiento->calle,
                        'numero' => $establecimiento->numero,
                        'codigo_postal' => $establecimiento->codigo_postal,
                        'ciudad' => $establecimiento->ciudad,
                        'provincia' => $establecimiento->provincia,
                        'referencia' => $establecimiento->referencia,
                        'direccion_completa' => $establecimiento->direccion_completa,
                        'latitud' => $establecimiento->latitud,
                        'longitud' => $establecimiento->longitud
                    ],
                    'datos_legales' => [
                        'razon_social' => $datosClaveNegocio->razon_social,
                        'ruc' => $datosClaveNegocio->ruc
                    ],
                    'datos_bancarios' => [
                        'titular_cuenta' => $datosBancarios->titular_cuenta,
                        'numero_cuenta' => $datosBancarios->numero_cuenta,
                        'nombre_banco' => $datosBancarios->nombre_banco,
                        'tipo_cuenta' => $datosBancarios->tipo_cuenta,
                        'documento_titular' => $datosBancarios->documento_titular,
                        'codigo_cci' => $datosBancarios->codigo_cci,
                        'usar_direccion_negocio' => $datosBancarios->usar_direccion_negocio
                    ]
                ]);
            }

            return response()->json([
                'error' => 'Token no proporcionado'
            ], 401);

        } catch (\Exception $e) {
            Log::error('Error en obtenerDatosRevision: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los datos',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}

