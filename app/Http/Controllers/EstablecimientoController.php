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
    public function update(Request $request, Establecimiento $establecimiento)
{
    try {
        $establecimiento->update([
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
        ]);

        return response()->json([
            'message' => 'Establecimiento actualizado correctamente',
            'establecimiento' => $establecimiento
        ]);
    } catch (\Exception $e) {
        Log::error('Error al actualizar establecimiento: ' . $e->getMessage());
        return response()->json(['error' => 'Error al actualizar el establecimiento'], 500);
    }
}
public function show($businessRegistrationId)
{
    try {
        $establecimiento = Establecimiento::where('business_registration_id', $businessRegistrationId)
            ->first();
            
        if (!$establecimiento) {
            return response()->json(['message' => 'Establecimiento no encontrado'], 404);
        }

        // Transformamos los datos al formato esperado por el frontend
        return response()->json([
            'nombre_establecimiento' => $establecimiento->nombre_establecimiento,
            'calle' => $establecimiento->calle,
            'numero' => $establecimiento->numero,
            'codigo_postal' => $establecimiento->codigo_postal,
            'provincia' => $establecimiento->provincia,
            'ciudad' => $establecimiento->ciudad,
            'referencia' => $establecimiento->referencia,
            'latitud' => $establecimiento->latitud,
            'longitud' => $establecimiento->longitud,
            'direccion_completa' => $establecimiento->direccion_completa,
            'id' => $establecimiento->id,
            'business_registration_id' => $establecimiento->business_registration_id
        ]);
    } catch (\Exception $e) {
        Log::error('Error al obtener establecimiento: ' . $e->getMessage());
        return response()->json(['error' => 'Error al obtener los datos del establecimiento'], 500);
    }
}

}

