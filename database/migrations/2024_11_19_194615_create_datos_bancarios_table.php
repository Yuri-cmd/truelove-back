<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('datos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_registration_id')->constrained('business_registrations')->onDelete('cascade');
            $table->string('titular_cuenta');
            $table->string('numero_cuenta');
            $table->string('nombre_banco');
            $table->string('tipo_cuenta');
            $table->string('documento_titular');
            $table->string('codigo_cci');
            $table->boolean('usar_direccion_negocio')->default(value: true);
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('datos_bancarios');
    }
};