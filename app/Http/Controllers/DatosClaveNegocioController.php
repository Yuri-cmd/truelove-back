<?php

namespace App\Http\Controllers;

use App\Models\DatosClaveNegocio;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DatosClaveNegocioController extends Controller
{
    public function guardar(Request $request)
    {
        Log::info('Datos recibidos:', $request->all());

        $validador = Validator::make($request->all(), [
            'ruc' => 'required|string|size:11',
            'razon_social' => 'required|string|max:255',
            'business_registration_id' => 'required|exists:business_registrations,id'
        ]);

        if ($validador->fails()) {
            Log::error('Validación fallida:', $validador->errors()->toArray());
            return response()->json([
                'mensaje' => 'Error de validación',
                'errores' => $validador->errors()
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

            $datosClaveNegocio = DatosClaveNegocio::create([
                'ruc' => $request->ruc,
                'razon_social' => $request->razon_social,
                'business_registration_id' => $request->business_registration_id
            ]);

            DB::commit();

            Log::info('Datos guardados exitosamente:', $datosClaveNegocio->toArray());

            return response()->json([
                'mensaje' => 'Datos guardados exitosamente',
                'datos' => $datosClaveNegocio
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar datos:', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);

            return response()->json([
                'mensaje' => 'Error al guardar los datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

