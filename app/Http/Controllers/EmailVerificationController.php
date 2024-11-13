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
        $request->validate([
            'name' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'businessType' => 'required|string',
            'phone' => 'required|string',
            'email' => 'required|email|unique:business_registrations,email',
        ]);

        // Generate verification code
        $verificationCode = Str::random(6);

        // Create registration record
        $registration = BusinessRegistration::create([
            'name' => $request->name,
            'lastName' => $request->lastName,
            'businessType' => $request->businessType,
            'phone' => $request->phone,
            'email' => $request->email,
            'verification_code' => $verificationCode,
        ]);

        // Send verification email
        Mail::send('emails.verification', ['code' => $verificationCode,'name'=>$request->name ], function($message) use ($request) {
            $message->to($request->email)
                    ->subject('Verificación de correo electrónico - TRUELOVE');
        });

        return response()->json([
            'message' => 'Código de verificación enviado al correo electrónico',
            'registration_id' => $registration->id
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'registration_id' => 'required|exists:business_registrations,id',
            'code' => 'required|string',
        ]);

        $registration = BusinessRegistration::find($request->registration_id);

        if ($registration->verification_code !== $request->code) {
            return response()->json([
                'message' => 'Código de verificación inválido'
            ], 400);
        }

        $registration->email_verified_at = now();
        $registration->save();

        return response()->json([
            'message' => 'Email verificado exitosamente'
        ]);
    }
    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:business_registrations,email',
            'registration_id' => 'required|exists:business_registrations,id',
        ]);

        $registration = BusinessRegistration::find($request->registration_id);

        if ($registration->email !== $request->email) {
            return response()->json([
                'message' => 'El correo electrónico no coincide con el registro'
            ], 400);
        }

        // Generar nuevo código de verificación
        $newVerificationCode = Str::random(6);
        $registration->verification_code = $newVerificationCode;
        $registration->save();

        // Enviar nuevo correo de verificación
        Mail::send('emails.verification', ['code' => $newVerificationCode, 'name' => $registration->name], function($message) use ($request) {
            $message->to($request->email)
                    ->subject('Nuevo código de verificación - TRUELOVE');
        });

        return response()->json([
            'message' => 'Nuevo código de verificación enviado al correo electrónico'
        ]);
    }
}