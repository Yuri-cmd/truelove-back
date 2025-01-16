<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthAdminController extends Controller
{
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'usuario' => 'required',
                'password' => 'required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Credenciales incorrectas.',
            ], 422);
        }
    
        $user = User::where('usuario', $credentials['usuario'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'error' => 'Credenciales incorrectas.',
            ], 401);
        }
    
        if (!$user->estado) {
            return response()->json([
                'error' => 'Usuario Inactivo',
            ], 401);
        }
    
        $token = $user->createToken(env('APP_NAME'))->plainTextToken;
    
        // Obtener el rol del usuario
        $roleName = $user->role ? $user->role->name : 'user';
    
        return response()->json([
            'token' => $token, 
            'user' => $user,
            'role' => $roleName
        ]);
    }
}