<?php

namespace App\Http\Controllers;

use App\Mail\SoporteMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SoporteController extends Controller
{
    public function enviarConsulta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'tipoConsulta' => 'required|string',
            'asunto' => 'required|string|max:255',
            'mensaje' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $datos = [
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'email' => $request->email,
                'telefono' => $request->telefono ?? 'No proporcionado',
                'tipoConsulta' => $this->getTipoConsultaLabel($request->tipoConsulta),
                'asunto' => $request->asunto,
                'mensaje' => $request->mensaje,
                'fecha' => now()->format('d/m/Y H:i'),
            ];

            // Enviar correo al equipo de soporte
            Mail::to('info@deliverytruelove.com')->send(new SoporteMail($datos));

            return response()->json([
                'success' => true,
                'message' => 'Tu consulta ha sido enviada exitosamente. Te responderemos pronto.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al enviar tu consulta. Por favor, intenta nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getTipoConsultaLabel($tipo)
    {
        $tipos = [
            'soporte_tecnico' => 'Soporte Técnico',
            'problema_pedido' => 'Problema con Pedido',
            'registro' => 'Ayuda con Registro',
            'pagos' => 'Consulta sobre Pagos',
            'sugerencia' => 'Sugerencia',
            'otro' => 'Otro',
        ];

        return $tipos[$tipo] ?? $tipo;
    }
}
