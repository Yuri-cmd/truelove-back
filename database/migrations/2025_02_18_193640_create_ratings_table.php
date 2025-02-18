<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_pedido');
            $table->integer('restaurant_rating');
            $table->text('restaurant_comment')->nullable();
            $table->integer('motorcycle_rating');
            $table->text('motorcycle_comment')->nullable();
            $table->timestamps();

            $table->foreign('id_pedido')->references('id')->on('pedidos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ratings');
    }
};
