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
                $decoded = JWT::decode($token, new Key('P8zs3vF2xR9tN7yJ4mQ6bK1hG5wC0lA', 'HS256'));
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

                // Verificar que al menos los datos básicos estén completos
                if (!$negocio || !$establecimiento) {
                    return response()->json([
                        'error' => 'Datos básicos incompletos',
                        'detalles' => [
                            'negocio' => $negocio ? 'encontrado' : 'no encontrado',
                            'establecimiento' => $establecimiento ? 'encontrado' : 'no encontrado',
                            'datosClaveNegocio' => $datosClaveNegocio ? 'encontrado' : 'no encontrado',
                            'datosBancarios' => $datosBancarios ? 'encontrado' : 'no encontrado'
                        ]
                    ], 404);
                }

                // Preparar la respuesta con los datos disponibles
                $response = [
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
                    ]
                ];

                // Añadir datos clave si existen
                if ($datosClaveNegocio) {
                    $response['datos_legales'] = [
                        'razon_social' => $datosClaveNegocio->razon_social,
                        'ruc' => $datosClaveNegocio->ruc
                    ];
                } else {
                    $response['datos_legales'] = [
                        'razon_social' => 'No especificado (omitido)',
                        'ruc' => 'No especificado (omitido)'
                    ];
                }

                // Añadir datos bancarios si existen
                if ($datosBancarios) {
                    $response['datos_bancarios'] = [
                        'titular_cuenta' => $datosBancarios->titular_cuenta,
                        'numero_cuenta' => $datosBancarios->numero_cuenta,
                        'nombre_banco' => $datosBancarios->nombre_banco,
                        'tipo_cuenta' => $datosBancarios->tipo_cuenta,
                        'documento_titular' => $datosBancarios->documento_titular,
                        'codigo_cci' => $datosBancarios->codigo_cci,
                        'usar_direccion_negocio' => $datosBancarios->usar_direccion_negocio
                    ];
                } else {
                    $response['datos_bancarios'] = [
                        'titular_cuenta' => 'No especificado (omitido)',
                        'numero_cuenta' => 'No especificado (omitido)',
                        'nombre_banco' => 'No especificado (omitido)',
                        'tipo_cuenta' => 'No especificado (omitido)',
                        'documento_titular' => 'No especificado (omitido)',
                        'codigo_cci' => 'No especificado (omitido)',
                        'usar_direccion_negocio' => false
                    ];
                }

                return response()->json($response);
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
