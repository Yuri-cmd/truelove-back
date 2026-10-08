<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // El número de contacto (WhatsApp, o el celular si no hay WhatsApp) se verificó con un código
            $table->boolean('numero_validado')->default(false);
            // Cuál número se validó: si el cliente lo cambia, deja de contar como validado
            $table->string('numero_validado_celular', 20)->nullable();
            $table->timestamp('numero_validado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['numero_validado', 'numero_validado_celular', 'numero_validado_en']);
        });
    }
};
