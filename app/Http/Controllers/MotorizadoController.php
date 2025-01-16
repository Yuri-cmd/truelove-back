<?php

namespace App\Http\Controllers;

use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        try {
            $motorizado = RepartoRegistro::findOrFail($id);
            $motorizado->aprobado = true;
            $motorizado->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Motorizado aprobado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al aprobar motorizado: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar el motorizado'
            ], 500);
        }
    }
}