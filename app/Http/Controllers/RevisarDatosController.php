<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\Establecimiento;
use App\Models\DatosClaveNegocio;
use App\Models\DatosBancarios;
use Illuminate\Http\Request;

class RevisarDatosController extends Controller
{
    public function obtenerDatosRevision(Request $request)
    {
        try {
            // Get parameters with fallbacks for different naming conventions
            $negocioId = $request->query('negocioId') ?? $request->query('negocio_id');
            $establecimientoId = $request->query('establecimientoId') ?? $request->query('establecimiento_id');
            $datosClaveId = $request->query('datosClaveId') ?? $request->query('datos_clave_id');
            $datosBancariosId = $request->query('datosBancariosId') ?? $request->query('datos_bancarios_id');

            // Validate parameters
            if (!$negocioId || !$establecimientoId || !$datosClaveId || !$datosBancariosId) {
                return response()->json([
                    'error' => 'Faltan parámetros requeridos',
                    'parametros_recibidos' => [
                        'negocioId' => $negocioId,
                        'establecimientoId' => $establecimientoId,
                        'datosClaveId' => $datosClaveId,
                        'datosBancariosId' => $datosBancariosId
                    ]
                ], 400);
            }

            // Get all required data
            $negocio = Negocio::with(['tipoNegocio', 'categoria'])->find($negocioId);
            $establecimiento = Establecimiento::find($establecimientoId);
            $datosClaveNegocio = DatosClaveNegocio::find($datosClaveId);
            $datosBancarios = DatosBancarios::find($datosBancariosId);

            // Validate if all records exist
            if (!$negocio || !$establecimiento || !$datosClaveNegocio || !$datosBancarios) {
                return response()->json([
                    'error' => 'Uno o más registros no encontrados',
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
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener los datos',
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}