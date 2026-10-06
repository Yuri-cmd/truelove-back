<?php

namespace App\Console\Commands;

use App\Models\WhatsappLog;
use Illuminate\Console\Command;

/**
 * Ningún mensaje de WhatsApp debe quedar "abierto" para siempre: si Meta no confirmó su estado
 * final en un tiempo razonable se cierra con un estado explícito.
 */
class CerrarWhatsappPendientes extends Command
{
    protected $signature = 'whatsapp:cerrar-pendientes {--horas=24 : Horas sin confirmación antes de cerrar un envío}';

    protected $description = 'Cierra los logs de WhatsApp que quedaron pendientes o sin confirmación de Meta';

    public function handle(): int
    {
        // "pendiente" tras 10 minutos: el envío no llegó a completarse (error interno o corte)
        $huerfanos = WhatsappLog::where('direccion', WhatsappLog::SALIENTE)
            ->where('estado', 'pendiente')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update([
                'estado' => 'fallido',
                'error_mensaje' => 'El envío no se completó',
                'cerrado_en' => now(),
            ]);

        // "enviado" o "entregado" sin llegar a leído tras N horas: Meta no confirmará más
        $sinConfirmar = WhatsappLog::where('direccion', WhatsappLog::SALIENTE)
            ->whereIn('estado', ['enviado', 'entregado'])
            ->where('created_at', '<', now()->subHours((int) $this->option('horas')))
            ->update(['estado' => 'sin_confirmacion', 'cerrado_en' => now()]);

        $this->info("Cerrados: {$huerfanos} incompletos, {$sinConfirmar} sin confirmación.");

        return self::SUCCESS;
    }
}
