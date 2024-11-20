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
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')
                  ->constrained('negocios')
                  ->onDelete('cascade');
            $table->string('nombre');
            $table->string('direccion');
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('es_sucursal_principal')->default(value: false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Índices para optimizar búsquedas
            $table->index('negocio_id');
            $table->index(['latitud', 'longitud']);
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};