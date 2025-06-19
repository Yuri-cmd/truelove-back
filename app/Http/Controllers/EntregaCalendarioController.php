<?php

namespace App\Http\Controllers;

use App\Models\EntregaCalendario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EntregaCalendarioController extends Controller
{
    public function agendarEntrega(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'reparto_registro_id' => 'required|exists:reparto_registros,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entrega = EntregaCalendario::create($validator->validated());

        return response()->json([
            'message' => 'Entrega agendada exitosamente',
            'data' => $entrega
        ], 201);
    }
    public function actualizarEstado(Request $request, $id)
    {
        try {
            $entrega = EntregaCalendario::findOrFail($id);
            $entrega->estado = $request->estado;
            $entrega->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de entrega: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estado'
            ], 500);
        }
    }
}