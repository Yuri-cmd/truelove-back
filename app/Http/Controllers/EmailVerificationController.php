<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use App\Models\DocPdfExtranjero;
use App\Models\RepartoRegistro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    // registro del formulario principal

    public function register(Request $request)
    {
        try {
            Log::info('Datos de registro recibidos:', $request->all());
    
            // Verificar si existe un registro con el mismo correo y documento
            $existingRegistration = BusinessRegistration::where('email', $request->email)
                ->where('documentNumber', $request->documentNumber)
                ->first();
    
            // Si existe un registro con el mismo correo y documento, permitir continuar
            if ($existingRegistration) {
                // Verificar si el registro está completo
                $isComplete = $existingRegistration->negocio()->exists() &&
                    $existingRegistration->establecimiento()->exists() &&
                    $existingRegistration->datosClaveNegocio()->exists() &&
                    $existingRegistration->cuentaBancaria()->exists();
    
                if ($isComplete) {
                    return response()->json([
                        'message' => 'Este documento de identidad ya está registrado o se encuentra en uso.',
                        'error' => 'dni_registered'
                    ], 422);
                }
    
                return response()->json([
                    'message' => 'Registro incompleto encontrado',
                    'registration_id' => $existingRegistration->id,
                    'error' => 'incomplete_registration'
                ], 200);
            }
    
            // Verificar si existe un registro con el mismo documento pero diferente correo
            $existingDocument = BusinessRegistration::where('documentNumber', $request->documentNumber)
                ->where('email', '!=', $request->email)
                ->first();
            
            if ($existingDocument) {
                // Verificar si el registro está completo
                $isComplete = $existingDocument->negocio()->exists() &&
                    $existingDocument->establecimiento()->exists() &&
                    $existingDocument->datosClaveNegocio()->exists() &&
                    $existingDocument->cuentaBancaria()->exists();
    
                if ($isComplete) {
                    return response()->json([
                        'message' => 'Este documento de identidad ya está registrado o se encuentra en uso.',
                        'error' => 'dni_registered'
                    ], 422);
                }
    
                // Si el registro no está completo y el correo es diferente, devolver información para mostrar alerta
                return response()->json([
                    'message' => 'Ya existe un registro incompleto con este documento pero con otro correo electrónico.',
                    'registration_id' => $existingDocument->id,
                    'original_email' => $existingDocument->email,
                    'error' => 'different_email'
                ], 200);
            }
    
            // Verificar si existe un registro con el mismo correo pero diferente documento
            $existingEmail = BusinessRegistration::where('email', $request->email)
                ->where('documentNumber', '!=', $request->documentNumber)
                ->first();
            
            if ($existingEmail) {
                return response()->json([
                    'message' => 'Este correo electrónico ya está registrado.',
                    'error' => 'email_registered'
                ], 422);
            }
    
            // Validar los datos de entrada
            $validated = $request->validate([
                'documentType' => 'required|string|max:255',
                'documentNumber' => 'required|string|max:20',
                'name' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'businessType' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'email' => 'required|email|max:255',
                'antecedentesPenales' => 'required_if:documentType,CARNET_EXTRANJERIA|file|mimes:pdf|max:10240',
                'antecedentesPoliciales' => 'required_if:documentType,CARNET_EXTRANJERIA|file|mimes:pdf|max:10240',
              'posToDriver' => 'nullable|integer|in:0,1,2',
              'entrega_documento_venta' => 'nullable|integer|in:0,1,2',
            ]);
    
            // Verificar si existe en RepartoRegistro
            $existingReparto = RepartoRegistro::where('nro_documento', $request->documentNumber)
                ->orWhere('email', $request->email)
                ->first();
    
            if ($existingReparto) {
                $message = $existingReparto->nro_documento === $request->documentNumber
                    ? 'Este número de documento ya está registrado como repartidor'
                    : 'Este correo electrónico ya está registrado como repartidor';
    
                return response()->json([
                    'message' => $message,
                    'error' => 'duplicate_in_reparto'
                ], 422);
            }
    
            // Crear nuevo registro
            $verificationCode = Str::random(6);
            $registration = BusinessRegistration::create([
                'documentType' => $validated['documentType'],
                'documentNumber' => $validated['documentNumber'],
                'name' => $validated['name'],
                'lastName' => $validated['lastName'],
                'businessType' => $validated['businessType'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'verification_code' => $verificationCode,
               'posToDriver' => $request->has('posToDriver') ? (int)$request->posToDriver : 0,
                'entrega_documento_venta' => $request->has('entrega_documento_venta') ? (int)$request->entrega_documento_venta : 0,
            ]);
    
            // Función para almacenar PDF
            $almacenarPdf = function ($archivo, $carpeta) {
                if (!$archivo) return null;
                
                try {
                    $extension = $archivo->getClientOriginalExtension();
                    $nombreArchivo = time() . '_' . Str::random(10) . '.' . $extension;
                    
                    $ruta = Storage::disk('custom_public')->putFileAs(
                        'pdfs/antecedentes/' . $carpeta,
                        $archivo,
                        $nombreArchivo
                    );
                    
                    return $ruta;
                } catch (\Exception $e) {
                    Log::error("Error al guardar PDF en {$carpeta}: " . $e->getMessage());
                    throw $e;
                }
            };
    
            // Si es extranjero, guardar los PDFs
            if ($request->documentType === 'CARNET_EXTRANJERIA') {
                $antecedentesPenalesPath = $almacenarPdf($request->file('antecedentesPenales'), 'penales');
                $antecedentesPolicialesPath = $almacenarPdf($request->file('antecedentesPoliciales'), 'policiales');
    
                DocPdfExtranjero::create([
                    'business_registration_id' => $registration->id,
                    'antecedentes_penales_pdf' => $antecedentesPenalesPath,
                    'antecedentes_policiales_pdf' => $antecedentesPolicialesPath,
                ]);
            }
    
            // Enviar correo de verificación
            Mail::send('emails.verification', [
                'code' => $verificationCode,
                'name' => $validated['name']
            ], function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Verificación de correo electrónico - TRUELOVE');
            });
    
            return response()->json([
                'message' => 'Código de verificación enviado al correo electrónico',
                'registration_id' => $registration->id
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación: ' . json_encode($e->errors()));
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al registrar negocio: ' . $e->getMessage());
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
    
    public function updateEmail(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|max:255',
            ]);

            // Verificar si el correo ya existe en otro registro
            $existingEmail = BusinessRegistration::where('email', $validated['email'])
                ->where('id', '!=', $id)
                ->first();
            
            if ($existingEmail) {
                return response()->json([
                    'message' => 'Este correo electrónico ya está registrado.',
                    'error' => 'email_registered'
                ], 422);
            }

            // Verificar si existe en RepartoRegistro
            $existingReparto = RepartoRegistro::where('email', $validated['email'])->first();
            if ($existingReparto) {
                return response()->json([
                    'message' => 'Este correo electrónico ya está registrado como repartidor',
                    'error' => 'duplicate_in_reparto'
                ], 422);
            }

            // Actualizar el correo
            $registration = BusinessRegistration::findOrFail($id);
            $registration->email = $validated['email'];
            $registration->verification_code = Str::random(6); // Generar nuevo código
            $registration->email_verified_at = null; // Resetear verificación
            $registration->save();

            // Enviar nuevo correo de verificación
            Mail::send('emails.verification', [
                'code' => $registration->verification_code,
                'name' => $registration->name
            ], function ($message) use ($validated) {
                $message->to($validated['email'])
                    ->subject('Verificación de correo electrónico - TRUELOVE');
            });

            return response()->json([
                'message' => 'Correo electrónico actualizado correctamente',
                'registration_id' => $registration->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar correo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Hubo un problema al actualizar el correo electrónico. Por favor, intente nuevamente.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function getRegistration($id)
    {
        try {
            $registration = BusinessRegistration::findOrFail($id);
            
            return response()->json([
                'id' => $registration->id,
                'email' => $registration->email,
                'name' => $registration->name,
                'lastName' => $registration->lastName,
                'documentType' => $registration->documentType,
                'documentNumber' => $registration->documentNumber,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener registro: ' . $e->getMessage());
            return response()->json([
                'message' => 'Hubo un problema al obtener la información del registro.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

