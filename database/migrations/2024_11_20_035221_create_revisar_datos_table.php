<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('revisar_datos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_registration_id')->constrained('business_registrations')->onDelete('cascade');
            $table->foreignId('negocio_id')->constrained('negocios');
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->foreignId('datos_clave_negocio_id')->constrained('datos_clave_negocio');
            $table->foreignId('datos_bancarios_id')->constrained('datos_bancarios');
            $table->boolean('terminos_aceptados')->default(false);
            $table->timestamp('fecha_revision')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('negocio_id');
            $table->index('establecimiento_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('revisar_datos');
    }
};