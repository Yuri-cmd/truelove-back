<?php

namespace App\Console\Commands;

use App\Services\ImagenOptimizador;
use Illuminate\Console\Command;

class OptimizarImagenesNegocios extends Command
{
    protected $signature = 'negocios:optimizar-imagenes
        {--dry-run : Solo muestra qué imágenes se reducirían, sin tocar nada}
        {--sin-respaldo : No guarda copia de los originales}
        {--limite= : Procesa como máximo N imágenes (para probar con pocas antes de hacerlas todas)}';

    protected $description = 'Reduce el peso de los logos y banners de los negocios ya subidos';

    /** Carpeta pública => ancho máximo */
    private const CARPETAS = [
        'logos-negocio' => ImagenOptimizador::ANCHO_LOGO,
        'banners-negocio' => ImagenOptimizador::ANCHO_BANNER,
    ];

    /** Por debajo de este peso (y del ancho máximo) la imagen ya está bien. */
    private const UMBRAL_BYTES = 120 * 1024;

    public function handle(ImagenOptimizador $optimizador): int
    {
        // Las fotos grandes necesitan bastante memoria al decodificarlas
        @ini_set('memory_limit', '768M');

        $dry = (bool) $this->option('dry-run');
        $limite = (int) $this->option('limite');
        $respaldar = !$dry && !$this->option('sin-respaldo');
        $totalAntes = $totalDespues = $procesadas = $fallidas = 0;

        foreach (self::CARPETAS as $carpeta => $anchoMax) {
            $dir = public_path($carpeta);
            if (!is_dir($dir)) {
                $this->warn("No existe {$dir}");
                continue;
            }

            $archivos = array_filter(glob($dir . '/*') ?: [], fn ($f) => is_file($f)
                && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true));
            $this->info(sprintf('%s: %d archivos (ancho máx. %d px)', $carpeta, count($archivos), $anchoMax));

            foreach ($archivos as $archivo) {
                $peso = (int) filesize($archivo);
                $info = @getimagesize($archivo);
                $ancho = $info[0] ?? 0;
                if ($peso <= self::UMBRAL_BYTES && $ancho <= $anchoMax) {
                    continue; // ya es liviana
                }

                if ($limite > 0 && ($procesadas + $fallidas) >= $limite) {
                    $this->warn("Se alcanzó el límite de {$limite} imágenes. Quedan más por procesar: quita --limite para hacerlas todas.");
                    break 2;
                }

                $nombre = basename($archivo);
                if ($dry) {
                    $this->line(sprintf('  [dry-run] %s (%d KB, %d px)', $nombre, round($peso / 1024), $ancho));
                    $totalAntes += $peso;
                    $procesadas++;
                    continue;
                }

                // Sin respaldo verificado NO se toca la imagen: si no se pudo copiar el original
                // (disco lleno, sin permisos), se omite y se avisa.
                if ($respaldar && !$this->respaldar($archivo, $carpeta, $nombre)) {
                    $this->error("  {$nombre}: no se pudo respaldar el original, se deja sin cambios");
                    $fallidas++;
                    continue;
                }

                try {
                    $r = $optimizador->reducir($archivo, $anchoMax);
                } catch (\Throwable $e) {
                    $this->error("  {$nombre}: " . $e->getMessage());
                    continue;
                }

                if ($r['cambiado']) {
                    $totalAntes += $r['antes'];
                    $totalDespues += $r['despues'];
                    $procesadas++;
                    $this->line(sprintf('  %s: %d KB → %d KB (%d → %d px)', $nombre, round($r['antes'] / 1024),
                        round($r['despues'] / 1024), $r['ancho_antes'], $r['ancho_despues']));
                }
            }
        }

        if ($dry) {
            $this->info(sprintf('Se reducirían %d imágenes (%.1f MB en total).', $procesadas, $totalAntes / 1048576));
        } else {
            $this->info(sprintf('Listo: %d imágenes reducidas, de %.1f MB a %.1f MB.', $procesadas, $totalAntes / 1048576, $totalDespues / 1048576));
            if ($respaldar && $procesadas > 0) {
                $this->line('Originales guardados en storage/app/respaldo-imagenes/');
            }
        }

        if ($fallidas > 0) {
            $this->warn("{$fallidas} imágenes no se tocaron porque no se pudieron respaldar.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Copia el original al respaldo y comprueba que quedó completo. Devuelve false si falla. */
    private function respaldar(string $archivo, string $carpeta, string $nombre): bool
    {
        $destinoDir = storage_path('app/respaldo-imagenes/' . $carpeta);
        if (!is_dir($destinoDir) && !@mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
            return false;
        }

        $destino = $destinoDir . '/' . $nombre;
        if (is_file($destino)) {
            return true; // ya hay un respaldo (no se pisa el original verdadero con una versión ya reducida)
        }

        return @copy($archivo, $destino) && @filesize($destino) === @filesize($archivo);
    }
}
