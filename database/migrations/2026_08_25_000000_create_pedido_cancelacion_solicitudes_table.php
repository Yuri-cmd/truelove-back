<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pedido_cancelacion_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pedido_id');
            $table->unsignedTinyInteger('estado_pedido_al_solicitar');
            $table->text('motivo');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('solicitado_por_socio_id')->nullable();
            $table->unsignedBigInteger('revisado_por_admin_id')->nullable();
            $table->timestamp('revisado_at')->nullable();
            $table->timestamps();

            $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
            $table->foreign('revisado_por_admin_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pedido_cancelacion_solicitudes');
    }
};
