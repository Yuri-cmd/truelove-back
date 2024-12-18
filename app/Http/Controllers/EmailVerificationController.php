<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    public function register(Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'name' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'businessType' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:255|unique:business_registrations,email',
            ]);

            // Generar el código de verificación
            $verificationCode = Str::random(6);

            // Crear el registro de la inscripción
            $registration = BusinessRegistration::create([
                'name' => $request->name,
                'lastName' => $request->lastName,
                'businessType' => $request->businessType,
                'phone' => $request->phone,
                'email' => $request->email,
                'verification_code' => $verificationCode,
            ]);

            // Enviar el correo electrónico de verificación
            Mail::send('emails.verification', ['code' => $verificationCode, 'name' => $request->name], function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Verificación de correo electrónico - TRUELOVE');
            });

            // Responder con un mensaje de éxito
            return response()->json([
                'message' => 'Codigo de verificacion enviado al correo electronico',
                'registration_id' => $registration->id
            ]);
        } catch (\Exception $e) {
            // Capturar el error y devolver una respuesta con el mensaje de error
            return response()->json([
                'message' => 'Hubo un problema al registrar el negocio. Por favor, intente nuevamente.',
                'error' => $e->getMessage()  // Puedes opcionalmente incluir el mensaje de error para depuración
            ], 500);  // Código de error 500 para errores internos del servidor
        }
    }

    public function verify(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'registration_id' => 'required|exists:business_registrations,id',
                'code' => 'required|string',
            ]);

            // Buscar el registro de la empresa
            $registration = BusinessRegistration::find($request->registration_id);

            // Verificar el código
            if ($registration->verification_code !== $request->code) {
                return response()->json([
                    'message' => 'Codigo de verificacion invalido'
                ], 400);
            }

            // Marcar como verificado el correo
            $registration->email_verified_at = now();
            $registration->save();

            // Guardar el ID en la sesión para el siguiente paso
        session(['business_registration_id' => $registration->id]);
            return response()->json([
                'message' => 'Email verificado exitosamente',
                'business_registration_id' => $registration->id // También lo enviamos en la respuesta
            ]);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y devolver un mensaje de error genérico
            return response()->json([
                'message' => 'Hubo un problema al verificar el correo. Por favor, intente nuevamente.',
                'error' => $e->getMessage() // Esto te ayudará a depurar
            ], 500);
        }
    }

    public function resendCode(Request $request)
    {
        try {
            // Validar los datos recibidos en la solicitud
            $request->validate([
                'email' => 'required|email|exists:business_registrations,email',
                'registration_id' => 'required|exists:business_registrations,id',
            ]);

            // Buscar el registro de la empresa
            $registration = BusinessRegistration::find($request->registration_id);

            // Verificar si el correo electrónico coincide con el registrado
            if ($registration->email !== $request->email) {
                return response()->json([
                    'message' => 'El correo electronico no coincide con el registro'
                ], 400);
            }

            // Generar nuevo código de verificación
            $newVerificationCode = Str::random(6);
            $registration->verification_code = $newVerificationCode;
            $registration->save();

            // Enviar el nuevo correo de verificación
            Mail::send('emails.verification', ['code' => $newVerificationCode, 'name' => $registration->name], function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Nuevo codigo de verificacion - TRUELOVE');
            });

            return response()->json([
                'message' => 'Nuevo codigo de verificacion enviado al correo electronico'
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
