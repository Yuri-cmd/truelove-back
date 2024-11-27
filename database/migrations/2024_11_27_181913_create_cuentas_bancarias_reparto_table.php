<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cuentas_bancarias_reparto', function (Blueprint $table) {
            $table->id();
            $table->string('titular');
            $table->string('dni');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->foreignId('tipo_cuenta_id')->constrained('tipos_cuenta_bancaria');
            $table->string('numero_cuenta');
            $table->string('url_imagen_cuenta');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias_reparto');
    }
};
