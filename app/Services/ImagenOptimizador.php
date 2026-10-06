<?php

namespace App\Services;

/**
 * Reduce el peso de las imágenes de los negocios (logos y banners) con GD, sin
 * cambiar su nombre ni su formato, para que las URLs existentes sigan valiendo.
 *
 * Los logos se muestran de ~50 px y los banners de ~500 px, pero los negocios suben
 * fotos de varios MB; cada visita a la lista de locales las descargaba enteras.
 */
class ImagenOptimizador
{
    /** Ancho máximo (px) de un logo: se muestra pequeño, 400 px cubre pantallas retina. */
    public const ANCHO_LOGO = 400;

    /** Ancho máximo (px) de un banner. */
    public const ANCHO_BANNER = 1280;

    /**
     * Reduce la imagen en el mismo archivo: la escala si supera $anchoMax y la vuelve a
     * comprimir. Solo reemplaza el archivo si el resultado pesa menos.
     *
     * @return array{cambiado: bool, antes: int, despues: int, ancho_antes: int, ancho_despues: int}
     */
    public function reducir(string $ruta, int $anchoMax, int $calidad = 80): array
    {
        $antes = (int) @filesize($ruta);
        $sinCambios = ['cambiado' => false, 'antes' => $antes, 'despues' => $antes, 'ancho_antes' => 0, 'ancho_despues' => 0];

        $info = @getimagesize($ruta);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return $sinCambios;
        }

        [$ancho, $alto, $tipo] = $info;
        $sinCambios['ancho_antes'] = $sinCambios['ancho_despues'] = $ancho;

        $imagen = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($ruta),
            IMAGETYPE_PNG => @imagecreatefrompng($ruta),
            IMAGETYPE_WEBP => @imagecreatefromwebp($ruta),
        };
        if (!$imagen) {
            return $sinCambios;
        }

        if ($tipo === IMAGETYPE_JPEG) {
            $imagen = $this->aplicarOrientacion($imagen, $ruta);
            $ancho = imagesx($imagen);
        }

        $escalada = false;
        if ($ancho > $anchoMax) {
            $nueva = imagescale($imagen, $anchoMax, -1, IMG_BICUBIC);
            if ($nueva) {
                imagedestroy($imagen);
                $imagen = $nueva;
                $escalada = true;
            }
        }

        $temporal = $ruta . '.tmp';
        $guardada = match ($tipo) {
            IMAGETYPE_JPEG => imagejpeg($imagen, $temporal, $calidad),
            IMAGETYPE_PNG => $this->guardarPng($imagen, $temporal),
            IMAGETYPE_WEBP => imagewebp($imagen, $temporal, $calidad),
        };
        $anchoFinal = imagesx($imagen);
        imagedestroy($imagen);

        if (!$guardada || !is_file($temporal)) {
            @unlink($temporal);
            return $sinCambios;
        }

        $despues = (int) filesize($temporal);

        // Solo se reemplaza si se escaló o si realmente quedó más liviana
        if ($escalada || $despues < $antes) {
            rename($temporal, $ruta);
            return ['cambiado' => true, 'antes' => $antes, 'despues' => $despues, 'ancho_antes' => $info[0], 'ancho_despues' => $anchoFinal];
        }

        @unlink($temporal);
        return $sinCambios;
    }

    private function guardarPng($imagen, string $destino): bool
    {
        // Conserva la transparencia
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        return imagepng($imagen, $destino, 9);
    }

    /** Las fotos de celular traen la rotación en EXIF; al recomprimir con GD se pierde, así que se aplica. */
    private function aplicarOrientacion($imagen, string $ruta)
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }
        $exif = @exif_read_data($ruta);
        $orientacion = (int) ($exif['Orientation'] ?? 1);

        $grados = match ($orientacion) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($grados !== 0) {
            $rotada = imagerotate($imagen, $grados, 0);
            if ($rotada) {
                imagedestroy($imagen);
                return $rotada;
            }
        }
        return $imagen;
    }
}
