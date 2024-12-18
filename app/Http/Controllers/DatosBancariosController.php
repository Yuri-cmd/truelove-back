<?php

namespace App\Http\Controllers;

use App\Models\DatosBancarios;
use App\Models\Establecimiento;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DatosBancariosController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titular_cuenta' => 'required|string|max:255',
            'numero_cuenta' => 'required|string|max:255',
            'nombre_banco' => 'required|string|max:255',
            'tipo_cuenta' => 'required|string|max:255',
            'documento_titular' => 'required|string|max:255',
            'codigo_cci' => 'required|string|max:255',
            'usar_direccion_negocio' => 'required|boolean',
            'establecimiento_id' => 'required|exists:establecimientos,id',
            'business_registration_id' => 'required|exists:business_registrations,id'
        ]);

        if ($validator->fails()) {
            Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Verificar que el registro existe y está verificado
            $businessRegistration = BusinessRegistration::where('id', $request->business_registration_id)
                ->whereNotNull('email_verified_at')
                ->first();

            if (!$businessRegistration) {
                throw new \Exception('Registro de negocio no encontrado o email no verificado');
            }

            $datosBancarios = DatosBancarios::create([
                'titular_cuenta' => $request->titular_cuenta,
                'numero_cuenta' => $request->numero_cuenta,
                'nombre_banco' => $request->nombre_banco,
                'tipo_cuenta' => $request->tipo_cuenta,
                'documento_titular' => $request->documento_titular,
                'codigo_cci' => $request->codigo_cci,
                'usar_direccion_negocio' => $request->usar_direccion_negocio,
                'establecimiento_id' => $request->establecimiento_id,
                'business_registration_id' => $request->business_registration_id
            ]);

            DB::commit();

            return response()->json([
                'mensaje' => 'Datos bancarios guardados exitosamente',
                'datos' => $datosBancarios
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar datos bancarios: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al guardar los datos bancarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEstablecimientoDireccion($id)
    {
        try {
            $establecimiento = Establecimiento::findOrFail($id);
            
            return response()->json([
                'direccion' => [
                    'calle' => $establecimiento->calle,
                    'numero' => $establecimiento->numero,
                    'codigo_postal' => $establecimiento->codigo_postal,
                    'provincia' => $establecimiento->provincia,
                    'ciudad' => $establecimiento->ciudad,
                    'referencia' => $establecimiento->referencia,
                    'direccion_completa' => $establecimiento->direccion_completa
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener dirección del establecimiento: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'Error al obtener la dirección del establecimiento',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}

