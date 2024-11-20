<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
            $table->string('calle');
            $table->string('numero');
            $table->string('codigo_postal');
            $table->string('provincia');
            $table->string('ciudad');
            $table->string('referencia')->nullable();
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->string('direccion_completa');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('establecimientos');
    }
};