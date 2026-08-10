<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\BusinessRegistration;
use App\Models\User;

/**
 * Middleware de autenticación flexible para socios.
 * Permite que tanto la web como el app Flutter usen las mismas rutas de API.
 */
class AuthSocioFlexible
{
    /**
     * Acepta autenticación por:
     * 1. Bearer token (Sanctum) - usado por la web del socio
     * 2. Header X-Socio-Id o parámetro ?socio_id= - usado por el app Flutter
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Intentar autenticación por Sanctum (Bearer token)
        if ($request->bearerToken()) {
            $user = auth('sanctum')->user();
            if ($user) {
                auth()->setUser($user);
                return $next($request);
            }
        }

        // 2. Fallback: socio_id desde header o parámetro
        $socioId = $request->header('X-Socio-Id') ?? $request->input('socio_id');

        if ($socioId) {
            $businessReg = BusinessRegistration::find($socioId);

            if ($businessReg && $businessReg->user_id) {
                $user = User::find($businessReg->user_id);

                if ($user) {
                    auth()->setUser($user);
                    return $next($request);
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No autenticado. Envíe Bearer token o X-Socio-Id header.'
        ], 401);
    }
}
