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
        Schema::create('adicionales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('categoria_adicional_id');
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('foto');
            $table->decimal('precio', 10, 2);
            $table->enum('status', ['active', 'inactive']);
            $table->timestamps();
        
            $table->foreign('empresa_id')->references('id')->on('business_registrations')->onDelete('cascade');
            $table->foreign('categoria_adicional_id')->references('id')->on('categoria_adicional')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adicionales');
    }
};
