<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    public function sendCode(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'email' => 'required|email',
            ]);

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);

            Mail::to($request->email)->send(new SendCode($request->email, $newVerificationCode));


            return response()->json([
                'message' => 'Nuevo codigo de verificacion enviado al correo electronico',
                'status' => 200,
            ]);
        } catch (\Exception $e) {
            // Capturar cualquier error y devolver una respuesta de error
            return response()->json([
                'message' => 'Hubo un problema al reenviar el codigo de verificacion. Por favor, intente nuevamente.',
                'error' => $e->getMessage() // Detalle del error para depuración
            ], 500);
        }
    }
}
