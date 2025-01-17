<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

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

        // Verificar si existe el usuario y si la contraseña es correcta
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
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
    
        // Crear token de autenticación
        $token = $user->createToken(env('APP_NAME'))->plainTextToken;
    
        // Obtener el rol del usuario
        $roleName = $user->role ? $user->role->name : 'user';
    
        // Retornar respuesta exitosa
        return response()->json([
            'token' => $token, 
            'user' => $user,
            'role' => $roleName
        ]);
    }

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
            ]);

            // Buscar el usuario por email
            $user = User::where('email', $request->email)->first();

            // Verificar si existe el usuario
            if (!$user) {
                return response()->json([
                    'error' => 'No se encontró un usuario con ese correo electrónico.',
                ], 404);
            }

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
            ], 500);
        }
    }

    /**
     * Envía el correo de restablecimiento de contraseña
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
   /**
     * Verifica si existe un usuario con el correo electrónico proporcionado
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
                return response()->json([
                    'message' => 'Correo encontrado exitosamente',
                    'exists' => true
                ]);
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
            return response()->json([
                'error' => 'Error al verificar el correo electrónico'
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