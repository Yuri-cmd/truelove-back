<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    
    public function register(Request $request)
    {
        try {
            // Validar los datos de entrada incluyendo documento único
            $validated = $request->validate([
                'documentType' => 'required|string|max:255',
                'documentNumber' => [
                    'required',
                    'string',
                    'max:20',
                    
                    Rule::unique('business_registrations')->where(function ($query) use ($request) {
                        return $query->where('documentType', $request->documentType) // Verificar el tipo de documento
                                   ->where('documentNumber', $request->documentNumber); // Verificar el número de documento
                    })
                ],
                'name' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'businessType' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:255|unique:business_registrations,email',
            ], [
                'documentNumber.unique' => 'Este documento de identidad ya está registrado o se encuentra en uso.',
                'email.unique' => 'Este correo electrónico ya está registrado. Por favor, intente con otro.'
            ]);
            //verifica si hay duplicados en la tabla de RepartoRegistro

            $existingReparto = RepartoRegistro::where('nro_documento', $request->documentNumber)
            ->orWhere('email', $request->email)
            ->first();

            // si existe un duplicado en la tabla de RepartoRegistro retorna un mensaje de error
            if ($existingReparto){
                $message = $existingReparto->nro_documento === $request->documentNumber
                ? 'Este número de documento ya está registrado como repartidor'
                : 'Este correo electrónico ya está registrado como repartidor';

                return response()->json([
                    'message' => $message,
                  'error' => 'duplicate_in_reparto' // se usa duplicate_in_reparto para identificar el error
                ], 422);
            }

            // Generar el código de verificación
            $verificationCode = Str::random(6);

            // Crear el registro de la inscripción
            $registration = BusinessRegistration::create([
                'documentType' => $validated['documentType'],
                'documentNumber' => $validated['documentNumber'],
                'name' => $validated['name'],
                'lastName' => $validated['lastName'],
                'businessType' => $validated['businessType'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'verification_code' => $verificationCode,
            ]);

            // Enviar el correo electrónico de verificación
            Mail::send('emails.verification', ['code' => $verificationCode, 'name' => $validated['name']], function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Verificación de correo electrónico - TRUELOVE');
            });

            return response()->json([
                'message' => 'Codigo de verificacion enviado al correo electronico',
                'registration_id' => $registration->id
            ]);

        } catch (ValidationException $e) {
            $errors = $e->validator->errors();
            
            // Verificar si el error es de documento duplicado
            if ($errors->has('documentNumber')) {
                return response()->json([
                    'message' => $errors->first('documentNumber'),
                    'error' => 'dni_registered'
                ], 422);
            }
            
            // Verificar si el error es de email duplicado
            if ($errors->has('email')) {
                return response()->json([
                    'message' => $errors->first('email'),
                    'error' => 'email_taken'
                ], 422);
            }

            // Otros errores de validación
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $errors->all()
            ], 422);

        } catch (\Exception $e) {
            // Otros errores no relacionados con la validación
            return response()->json([
                'message' => 'Hubo un problema al registrar el negocio. Por favor, intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
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
