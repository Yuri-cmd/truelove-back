<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('distritos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('ciudad_id')->constrained('ciudades')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('distritos');
    }
};

