<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Support\Facades\DB;

class EnlaceLocalController extends Controller
{
    /**
     * Página pública que se comparte desde la app: https://{dominio}/l/{id}.
     * Lleva las etiquetas Open Graph para que WhatsApp, Telegram, etc. muestren
     * una tarjeta con la foto y el nombre del local, y un botón que abre la app
     * (truelove://restaurante/{id}) o manda a la tienda si no está instalada.
     */
    public function show($idLocal)
    {
        $local = DB::selectOne(
            "SELECT
                establecimientos.nombre_establecimiento AS nombre,
                establecimientos.direccion_completa AS direccion,
                perfiles_negocio.ruta_logo AS logo,
                perfiles_negocio.banner AS banner,
                business_registrations.businessType AS tipo
            FROM business_registrations
            INNER JOIN establecimientos
                ON business_registrations.id = establecimientos.business_registration_id
            LEFT JOIN perfiles_negocio
                ON business_registrations.id = perfiles_negocio.business_registration_id
            WHERE business_registrations.id = ?
            LIMIT 1",
            [$idLocal]
        );

        if (!$local) {
            abort(404);
        }

        // Enlaces de descarga de la app cliente (tabla app_versions).
        $app = AppVersion::where('app_name', 'cliente')->first();

        $imagen = $this->urlImagen($local->banner ?: $local->logo);
        $nombre = mb_convert_case($local->nombre, MB_CASE_TITLE, 'UTF-8');

        return view('enlace-local', [
            'idLocal' => (int) $idLocal,
            'nombre' => $nombre,
            'direccion' => $local->direccion,
            'tipo' => $local->tipo,
            'imagen' => $imagen,
            'logo' => $this->urlImagen($local->logo),
            'enlace' => rtrim(config('services.app_links.public_url') ?: url('/'), '/') . "/l/{$idLocal}",
            'playStore' => $app?->url_android,
            'appStore' => $app?->url_ios,
        ]);
    }

    /** Verificación de App Links de Android. */
    public function assetLinks()
    {
        $huellas = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('services.app_links.android_sha256'))
        )));
        if (empty($huellas)) {
            abort(404);
        }

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('services.app_links.android_package'),
                'sha256_cert_fingerprints' => $huellas,
            ],
        ]]);
    }

    /** Verificación de Universal Links de iOS. */
    public function appleAppSiteAssociation()
    {
        $appId = config('services.app_links.ios_app_id');
        if (!$appId) {
            abort(404);
        }

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appIDs' => [$appId],
                    'components' => [['/' => '/l/*']],
                ]],
            ],
        ]);
    }

    private function urlImagen(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }

        return str_starts_with($ruta, 'http') ? $ruta : url($ruta);
    }
}
