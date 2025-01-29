<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('perfiles_negocio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_registration_id')->constrained()->onDelete('cascade');
            $table->string('ruta_logo')->nullable();
            $table->timestamps();
        });

        Schema::create('horarios_negocio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_negocio_id')->constrained('perfiles_negocio')->onDelete('cascade');
            $table->string('nombre');
            $table->boolean('lunes')->default(false);
            $table->boolean('martes')->default(false);
            $table->boolean('miercoles')->default(false);
            $table->boolean('jueves')->default(false);
            $table->boolean('viernes')->default(false);
            $table->boolean('sabado')->default(false);
            $table->boolean('domingo')->default(false);
            $table->time('hora_apertura');
            $table->time('hora_cierre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('horarios_negocio');
        Schema::dropIfExists('perfiles_negocio');
    }
};

