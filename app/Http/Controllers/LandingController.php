<?php

namespace App\Http\Controllers;

use App\Models\BusinessRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LandingController extends Controller
{
    /**
     * Métricas públicas para la landing de captación de negocios.
     * Cacheadas porque alimentan una página pública de alto tráfico.
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('landing.stats', now()->addHour(), function () {
            return [
                'negocios_activos' => BusinessRegistration::where('activo', 1)->count(),
                'pedidos_entregados' => DB::table('pedido_trackings')
                    ->where('estado', 8)
                    ->distinct('pedido_id')
                    ->count('pedido_id'),
            ];
        });

        return response()->json($stats);
    }
}
