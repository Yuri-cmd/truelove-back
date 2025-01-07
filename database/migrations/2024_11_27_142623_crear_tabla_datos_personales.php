<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('datos_personales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reparto_registro_id')->constrained('reparto_registros')->onDelete('cascade');
            $table->date('fecha_nacimiento');
            $table->string('genero');
            $table->string('url_selfie');
            $table->foreignId('ciudad_id')->constrained('ciudades');
            $table->foreignId('distrito_id')->constrained('distritos');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::table('datos_personales', function (Blueprint $table) {
            $table->dropForeign(['reparto_registro_id']);
            $table->dropColumn('reparto_registro_id');
        });
    }
};

