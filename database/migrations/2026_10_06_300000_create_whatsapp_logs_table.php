<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            // saliente: lo enviamos nosotros · entrante: nos escribieron (solo se registra, nunca se responde)
            $table->string('direccion', 10);
            $table->string('telefono', 20);
            $table->string('motivo', 50)->nullable();      // ej. verificacion
            $table->string('plantilla', 100)->nullable();
            $table->string('message_id')->nullable()->unique();
            // pendiente → enviado → entregado → leido | fallido | omitido_cuota | sin_confirmacion | recibido
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedInteger('http_status')->nullable();
            $table->string('error_codigo', 20)->nullable();
            $table->text('error_mensaje')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable()->index();
            $table->string('contenido', 255)->nullable();  // solo mensajes entrantes (truncado)
            $table->timestamp('cerrado_en')->nullable();   // cuando el mensaje llegó a un estado final
            $table->timestamps();

            $table->index(['direccion', 'created_at']);
            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
