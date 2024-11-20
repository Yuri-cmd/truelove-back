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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->foreignId('tipo_negocio_id')
                  ->constrained('tipos_negocios')
                  ->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Índices para optimizar búsquedas
            $table->index('nombre');
            $table->index('activo');
            $table->index('tipo_negocio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};