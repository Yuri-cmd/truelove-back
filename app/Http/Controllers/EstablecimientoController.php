<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EstablecimientoController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Datos recibidos:', $request->all());

        $validator = Validator::make($request->all(), [
            'businessName' => 'required|string|min:2',
            'street' => 'required|string|min:2',
            'number' => 'required|string|min:1',
            'postalCode' => 'required|string|min:5',
            'province' => 'required|string|min:2',
            'city' => 'required|string|min:2',
            'reference' => 'nullable|string',
            'coordinates' => 'required|array',
            'coordinates.0' => 'required|numeric',
            'coordinates.1' => 'required|numeric',
            'fullAddress' => 'required|string',
            'business_registration_id' => 'required|exists:business_registrations,id'
        ]);

        if ($validator->fails()) {
            Log::error('Validación fallida:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
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

            $establecimiento = Establecimiento::create([
                'nombre_establecimiento' => $request->businessName,
                'calle' => $request->street,
                'numero' => $request->number,
                'codigo_postal' => $request->postalCode,
                'provincia' => $request->province,
                'ciudad' => $request->city,
                'referencia' => $request->reference,
                'latitud' => $request->coordinates[1],
                'longitud' => $request->coordinates[0],
                'direccion_completa' => $request->fullAddress,
                'business_registration_id' => $request->business_registration_id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Establecimiento registrado exitosamente',
                'establecimiento' => $establecimiento
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear establecimiento: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al registrar el establecimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

