<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class OptimizeImages extends Command
{
    // El nombre del comando para la terminal
    protected $signature = 'images:optimize {--folder=public : La carpeta dentro de storage/app}';
    protected $description = 'Comprime imágenes pesadas en el storage';

    public function handle()
    {
        $folder = $this->option('folder');
        // Obtenemos todos los archivos del disco local
        $files = Storage::disk('public')->allFiles($folder);

        $this->info("Iniciando optimización de " . count($files) . " archivos...");

        foreach ($files as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];

            if (in_array($extension, $allowed)) {
                $fullPath = storage_path('app/public/' . $file);
                $sizeBefore = filesize($fullPath);

                // Solo optimizar si pesa más de 300KB
                if ($sizeBefore > 300 * 1024) {
                    $this->line("Procesando: $file (" . round($sizeBefore / 1024) . " KB)");

                    // PROCESO: Redimensionar y Comprimir
                    $img = Image::make($fullPath);

                    // 1. Si es muy ancha, la bajamos a 1200px (opcional)
                    $img->resize(1200, null, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                    // 2. Guardar con compresión (calidad 70)
                    $img->save($fullPath, 70);

                    // Limpiar memoria
                    $img->destroy();

                    $sizeAfter = filesize($fullPath);
                    $this->info("Hecho: " . round($sizeAfter / 1024) . " KB");
                }
            }
        }

        $this->info('¡Optimización finalizada!');
    }
}