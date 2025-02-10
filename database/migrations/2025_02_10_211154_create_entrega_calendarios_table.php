<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEntregaCalendariosTable extends Migration
{
    public function up()
    {
        Schema::create('entrega_calendarios', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->time('hora');
            $table->foreignId('reparto_registro_id')->constrained('reparto_registros');
            $table->string('estado')->default('pendiente');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('entrega_calendarios');
    }
}