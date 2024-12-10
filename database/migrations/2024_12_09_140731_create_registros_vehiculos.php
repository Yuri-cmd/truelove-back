<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('registros_vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa');
            $table->string('licencia_conducir');
            $table->string('seguro');
            $table->string('tarjeta_propiedad');
            $table->string('imagen_placa')->nullable();
            $table->string('imagen_licencia')->nullable();
            $table->string('imagen_seguro')->nullable();
            $table->string('imagen_tarjeta_propiedad')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('registros_vehiculos');
    }
};

