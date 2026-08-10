<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        
        if (!$user || !$user->hasRole($role)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para acceder a este recurso'
            ], 403);
        }

        return $next($request);
    }
}