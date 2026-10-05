<?php

namespace App\Console\Commands;

use App\Services\WhatsappCloudService;
use Illuminate\Console\Command;

class ProbarWhatsapp extends Command
{
    protected $signature = 'whatsapp:probar
        {telefono? : Destinatario (ej. 931372670 o 51931372670). Sin él, solo se verifica la conexión}
        {--plantilla=hello_world : Nombre de la plantilla aprobada}
        {--idioma=en_US : Código de idioma de la plantilla}
        {--params= : Valores de las variables de la plantilla, separados por coma}
        {--texto= : Enviar un texto libre en vez de plantilla (solo dentro de las 24 h de una conversación)}
        {--dry-run : Muestra lo que se enviaría, sin enviarlo}';

    protected $description = 'Prueba el envío de mensajes por la API de WhatsApp Cloud (Meta)';

    public function handle(WhatsappCloudService $whatsapp): int
    {
        if ($faltante = $whatsapp->configuracionFaltante()) {
            $this->error("Falta configurar {$faltante} en el .env");
            return self::FAILURE;
        }

        $telefono = $this->argument('telefono');
        $parametros = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('params'))), 'strlen'));

        // 1) Verificar token y Phone Number ID sin enviar nada
        if (!$telefono) {
            $this->info('Verificando token y Phone Number ID…');
            $r = $whatsapp->verificar();
            if ($r['ok']) {
                $this->info('✅ Conexión correcta');
                $this->line(json_encode($r['datos'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $this->line('Siguiente paso: php artisan whatsapp:probar <telefono>');
                return self::SUCCESS;
            }
            $this->error("❌ Meta respondió {$r['status']}");
            $this->mostrarError($r['datos']['error'] ?? []);
            return self::FAILURE;
        }

        // 2) Simulación
        if ($this->option('dry-run')) {
            $payload = $this->option('texto')
                ? ['messaging_product' => 'whatsapp', 'to' => $whatsapp->normalizarTelefono($telefono), 'type' => 'text', 'text' => ['body' => $this->option('texto')]]
                : $whatsapp->payloadPlantilla($telefono, $this->option('plantilla'), $this->option('idioma'), $parametros);
            $this->info('Se enviaría (no se envió nada):');
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        // 3) Envío real
        $destino = $whatsapp->normalizarTelefono($telefono);
        $r = $this->option('texto')
            ? $whatsapp->enviarTexto($telefono, $this->option('texto'))
            : $whatsapp->enviarPlantilla($telefono, $this->option('plantilla'), $this->option('idioma'), $parametros);

        if ($r['ok']) {
            $this->info("✅ Meta aceptó el mensaje para {$destino}");
            $this->line('ID del mensaje: ' . ($r['message_id'] ?? '—'));
            $this->line('(Que Meta lo acepte no garantiza que llegue: revisa el teléfono y los webhooks de estado.)');
            return self::SUCCESS;
        }

        $this->error("❌ Meta rechazó el envío (HTTP {$r['status']})");
        $this->mostrarError($r['error'] ?? []);
        return self::FAILURE;
    }

    private function mostrarError(array $error): void
    {
        $codigo = $error['code'] ?? null;
        $this->line('Código: ' . ($codigo ?? '—') . ' · ' . ($error['message'] ?? 'sin detalle'));

        $ayuda = match ((int) $codigo) {
            190 => 'El token venció o es inválido. Los tokens de prueba duran ~24 h: genera uno nuevo en Meta y actualiza META_TOKEN.',
            131030 => 'El número no está en la lista de destinatarios de prueba. Agrégalo en Meta > WhatsApp > Configuración de la API > Destinatario.',
            132000 => 'Cantidad de parámetros distinta a la de la plantilla. Ajusta --params.',
            132001 => 'La plantilla o el idioma no existen. Revisa --plantilla y --idioma (ej. en_US).',
            100 => 'Parámetro inválido o ID incorrecto. Revisa META_PHONE_NUMBER_ID.',
            default => null,
        };
        if ($ayuda) {
            $this->warn($ayuda);
        }
    }
}
