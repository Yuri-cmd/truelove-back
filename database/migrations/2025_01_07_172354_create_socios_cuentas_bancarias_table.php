<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSociosCuentasBancariasTable extends Migration
{
    public function up()
    {
        Schema::create('socios_cuentas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_registration_id')->constrained()->onDelete('cascade');
            $table->string('titular_cuenta');
            $table->string('dni');
            $table->foreignId('banco_id')->constrained('bancos');
            $table->foreignId('tipo_cuenta_id')->constrained('tipos_cuenta_bancaria');
            $table->string('numero_cuenta');
            $table->json('imagenes_cuenta');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('socios_cuentas_bancarias');
    }
}