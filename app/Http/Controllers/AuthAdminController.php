<?php

namespace App\Http\Controllers;
use App\Models\VerificationCode;
use App\Models\User;
use App\Mail\SendCodeLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AuthAdminController extends Controller
{
    /**
     * Maneja el proceso de inicio de sesión de usuarios
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validar las credenciales recibidas
            $credentials = $request->validate([
                'usuario' => 'required',
                'password' => 'required',
            ]);
                
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Credenciales incorrectas.',
            ], 422);
        }
    
        // Buscar el usuario por nombre de usuario
        $user = User::where('usuario', $credentials['usuario'])->first();
    
        // Si el usuario existe pero la contraseña es incorrecta
        if ($user && !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'error' => 'Contraseña incorrecta',
                'type' => 'wrong_password'
            ], 401);
        }
    
        // Si el usuario no existe
        if (!$user) {
            return response()->json([
                'error' => 'Credenciales incorrectas.',
            ], 401);
        }
    
        // Verificar si el usuario está activo
        if (!$user->estado) {
            return response()->json([
                'error' => 'Usuario Inactivo',
            ], 401);
        }
    
        try {
            // Obtener el business_registration si existe
            $businessRegistration = $user->businessRegistration;
            
            // Crear token de autenticación
            $token = $user->createToken(env('APP_NAME'))->plainTextToken;
        
            // Obtener el rol del usuario
            $roleName = $user->role ? $user->role->name : 'user';
        
            // Retornar respuesta exitosa con businessRegistration incluido
            return response()->json([
                'token' => $token, 
                'user' => [
                    'id' => $user->id,
                    'usuario' => $user->usuario,
                    'email' => $user->email,
                    'name' => $user->name,
                    'businessRegistration' => $businessRegistration ? [
                        'id' => $businessRegistration->id,
                        'name' => $businessRegistration->name,
                        'businessType' => $businessRegistration->businessType,
                    ] : null
                ],
                'role' => $roleName
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error en el servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * Verifica si existe un usuario con el correo electrónico proporcionado
     * y envía un código de verificación
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);

            $user = User::where('email', $request->email)->first();

            if ($user) {
                try {
                    // Generar código de verificación (6 dígitos)
                    $verificationCode = sprintf('%06d', mt_rand(1, 999999));
                    
                    // Guardar el código en la base de datos
                    // Primero eliminamos códigos anteriores para este email
                    VerificationCode::where('email', $request->email)->delete();
                    
                    // Creamos el nuevo registro
                    VerificationCode::create([
                        'email' => $request->email,
                        'code' => $verificationCode,
                        'expires_at' => now()->addMinutes(15)
                    ]);
                    
                    // Enviar el correo electrónico
                    try {
                        Mail::to($request->email)->send(new SendCodeLogin($verificationCode, $user->name ?? 'Usuario'));
                        
                        return response()->json([
                            'message' => 'Código de verificación enviado al correo electrónico',
                            'exists' => true
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Error al enviar correo: ' . $e->getMessage());
                        
                        // Para entorno de desarrollo, devolver el código en la respuesta
                        if (config('app.env') === 'local' || config('app.debug')) {
                            return response()->json([
                                'message' => 'Error al enviar correo, pero código generado (solo para desarrollo)',
                                'exists' => true,
                                'code' => $verificationCode // Solo para desarrollo
                            ]);
                        }
                        
                        return response()->json([
                            'error' => 'Error al enviar el correo electrónico',
                            'details' => $e->getMessage()
                        ], 500);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error en verificación de correo: ' . $e->getMessage());
                    
                    return response()->json([
                        'error' => 'Error al procesar la solicitud',
                        'details' => $e->getMessage()
                    ], 500);
                }
            } else {
                return response()->json([
                    'message' => 'No se encontró un usuario con ese correo electrónico',
                    'exists' => false
                ], 404);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error general en verificación: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al verificar el correo electrónico',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica el código de verificación proporcionado
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyCode(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'code' => 'required|string'
            ]);
    
            // Buscar el código en la base de datos
            $verificationData = VerificationCode::where('email', $request->email)
                ->where('code', $request->code)
                ->first();
            
            if (!$verificationData) {
                return response()->json([
                    'message' => 'Código de verificación inválido',
                    'valid' => false
                ], 400);
            }
            
            // Verificar si el código ha expirado
            if (now()->isAfter($verificationData->expires_at)) {
                $verificationData->delete();
                return response()->json([
                    'message' => 'El código de verificación ha expirado',
                    'valid' => false
                ], 400);
            }
            
            // NO eliminamos el código aquí, lo haremos en resetPassword
            // $verificationData->delete();
            
            return response()->json([
                'message' => 'Código de verificación válido',
                'valid' => true
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al verificar el código',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Envía un correo electrónico con el código de verificación
     * 
     * @param string $email
     * @param string $code
     * @return void
     */
   

    /**
     * Maneja el proceso de restablecimiento de contraseña
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        try {
            // Validar los datos recibidos
            $request->validate([
                'email' => 'required|email',
                'newPassword' => [
                    'required',
                    'min:8',
                    'regex:/[A-Z]/',    // Al menos una mayúscula
                    'regex:/[a-z]/',    // Al menos una minúscula
                    'regex:/[0-9]/',    // Al menos un número
                ],
                'verificationCode' => 'required|string',
            ]);
    
            // Buscar el usuario por email
            $user = User::where('email', $request->email)->first();
    
            // Verificar si existe el usuario
            if (!$user) {
                return response()->json([
                    'error' => 'No se encontró un usuario con ese correo electrónico.',
                ], 404);
            }
    
            // No verificamos el código nuevamente, pero sí lo eliminamos
            VerificationCode::where('email', $request->email)
                ->where('code', $request->verificationCode)
                ->delete();
    
            // Actualizar la contraseña
            $user->password = Hash::make($request->newPassword);
            $user->save();
    
            // Retornar respuesta exitosa
            return response()->json([
                'message' => 'Contraseña actualizada exitosamente',
            ]);
    
        } catch (ValidationException $e) {
            // Manejar errores de validación
            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Manejar otros errores
            return response()->json([
                'error' => 'Error al actualizar la contraseña',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica si el token de autenticación es válido y devuelve la información del usuario
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();
            $roleName = $user->role ? $user->role->name : 'user';

            return response()->json([
                'authenticated' => true,
                'user' => $user,
                'role' => $roleName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'authenticated' => false
            ], 401);
        }
    }
}