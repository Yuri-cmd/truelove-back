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
    public function getVersion($app_name)
    {
        $version = AppVersion::where('app_name', $app_name)->first();

        if (!$version) {
            return response()->json([
                'status' => 404,
                'message' => 'Configuración de versión no encontrada para: ' . $app_name
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => $version
        ]);
    }

    /**
     * Actualizar o crear una nueva versión (para uso administrativo).
     */
    public function updateVersion(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string',
            'min_version' => 'required|string',
            'latest_version' => 'required|string',
            'force_update' => 'required|boolean',
            'url_android' => 'nullable|string',
            'url_ios' => 'nullable|string',
        ]);

        $version = AppVersion::updateOrCreate(
            ['app_name' => $request->app_name],
            $request->only(['min_version', 'latest_version', 'force_update', 'url_android', 'url_ios'])
        );

        return response()->json([
            'status' => 200,
            'message' => 'Versión actualizada correctamente',
            'data' => $version
        ]);
    }
}
