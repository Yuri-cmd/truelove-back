<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reparto_registros', function (Blueprint $table) {
            $table->id();
            $table->string('departamento');
            $table->string('vehiculo');
            $table->string('tipo_documento');
            $table->string('nro_documento');
            $table->string('nombres');
            $table->string('apellidos')->nullable();
            $table->string('celular');
            $table->string('email');
            $table->boolean('mayor_edad');
            $table->boolean('acepta_politica');
            $table->text('documento_imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparto_registros');
    }
};

