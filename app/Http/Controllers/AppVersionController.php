<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    /**
     * Obtener la versión de la aplicación por su nombre.
     * 
     * @param string $app_name
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVersion(Request $request, $app_name)
    {
        $version = AppVersion::where('app_name', $app_name)->first();

        if (!$version) {
            return response()->json([
                'status' => 404,
                'message' => 'Configuración de versión no encontrada para: ' . $app_name
            ], 404);
        }

        // Detectar plataforma (parámetro query, header o User-Agent)
        $platform = $request->input('platform');
        if (!$platform) {
            $platform = $request->header('X-Platform');
        }
        if (!$platform) {
            $ua = $request->userAgent();
            if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'Darwin') !== false) {
                $platform = 'ios';
            } elseif (stripos($ua, 'Android') !== false) {
                $platform = 'android';
            }
        }

        $data = $version->toArray();

        // Si se detectó plataforma, sobreescribir los campos base para compatibilidad con apps viejas
        if ($platform === 'android') {
            $data['min_version'] = $version->min_version_android ?? $version->min_version;
            $data['latest_version'] = $version->latest_version_android ?? $version->latest_version;
            $data['force_update'] = $version->force_update_android ?? $version->force_update;
        } elseif ($platform === 'ios') {
            $data['min_version'] = $version->min_version_ios ?? $version->min_version;
            $data['latest_version'] = $version->latest_version_ios ?? $version->latest_version;
            $data['force_update'] = $version->force_update_ios ?? $version->force_update;
        }

        return response()->json([
            'status' => 200,
            'data' => $data,
            'detected_platform' => $platform // Útil para debugging
        ]);
    }

    /**
     * Actualizar o crear una nueva versión (para uso administrativo).
     */
    public function updateVersion(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string',
            'min_version' => 'nullable|string',
            'min_version_android' => 'nullable|string',
            'min_version_ios' => 'nullable|string',
            'latest_version' => 'nullable|string',
            'latest_version_android' => 'nullable|string',
            'latest_version_ios' => 'nullable|string',
            'force_update' => 'nullable|boolean',
            'force_update_android' => 'nullable|boolean',
            'force_update_ios' => 'nullable|boolean',
            'url_android' => 'nullable|string',
            'url_ios' => 'nullable|string',
        ]);

        $version = AppVersion::updateOrCreate(
            ['app_name' => $request->app_name],
            $request->all()
        );

        return response()->json([
            'status' => 200,
            'message' => 'Versión actualizada correctamente',
            'data' => $version
        ]);
    }
}
