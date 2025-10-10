<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PeriodoCuotaService;

class VerificarCuotaSocio
{
    protected $periodoCuotaService;

    public function __construct(PeriodoCuotaService $periodoCuotaService)
    {
        $this->periodoCuotaService = $periodoCuotaService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\Response)  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Si no es un socio, permitir acceso (para admins, motorizados, etc.)
        if (!$user || !$user->businessRegistration) {
            return $next($request);
        }

        $socio = $user->businessRegistration;

        // Verificar acceso del socio
        $resultado = $this->periodoCuotaService->verificarAccesoSocio($socio->id);

        // Si no puede acceder, retornar error
        if (!$resultado['puede_acceder']) {
            return response()->json([
                'success' => false,
                'bloqueado' => true,
                'motivo' => $resultado['motivo'],
                'mensaje' => $resultado['mensaje']
            ], 403);
        }

        // Si puede acceder, agregar información de advertencia al request
        if ($resultado['motivo'] === 'proximo_vencimiento') {
            $request->merge(['advertencia_vencimiento' => $resultado]);
        }

        return $next($request);
    }
}
