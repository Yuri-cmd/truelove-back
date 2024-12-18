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
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_registration_id')->constrained('business_registrations')->onDelete('cascade');
            $table->string('nombre');
            $table->foreignId('tipo_negocio_id')
                  ->constrained('tipos_negocios');
            $table->foreignId('categoria_id')
                  ->constrained('categorias');
            $table->foreignId('user_id')
                  ->constrained('users');
            $table->integer('total_sucursales')->default(1);
            $table->boolean('es_local_calle');
            $table->enum('metodo_contacto', ['WhatsApp', 'Llamada', 'SMS']);
            $table->string('telefono');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Índices para optimizar consultas frecuentes
            $table->index(['tipo_negocio_id', 'categoria_id']);
            $table->index('user_id');
            $table->index('activo');
            $table->index('nombre');
          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negocios');
    }
};