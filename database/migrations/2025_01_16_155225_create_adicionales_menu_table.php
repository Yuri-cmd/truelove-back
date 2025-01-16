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
        Schema::create('adicionales_menu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('adicional_id');
            $table->integer('cantidad');
            $table->timestamps();
        
            $table->foreign('menu_id')->references('id')->on('menu')->onDelete('cascade');
            $table->foreign('adicional_id')->references('id')->on('adicionales')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adicionales_menu');
    }
};
