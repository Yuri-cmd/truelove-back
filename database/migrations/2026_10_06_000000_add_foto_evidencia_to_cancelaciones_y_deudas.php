<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto de evidencia que el motorizado adjunta al cancelar un pedido. Se guarda en
     * la solicitud de cancelación y se copia a la deuda si el admin la genera.
     */
    public function up(): void
    {
        Schema::table('pedido_cancelacion_solicitudes', function (Blueprint $table) {
            $table->string('foto_evidencia')->nullable()->after('detalle');
        });

        Schema::table('cliente_deudas', function (Blueprint $table) {
            $table->string('foto_evidencia')->nullable()->after('observaciones_admin');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_cancelacion_solicitudes', function (Blueprint $table) {
            $table->dropColumn('foto_evidencia');
        });

        Schema::table('cliente_deudas', function (Blueprint $table) {
            $table->dropColumn('foto_evidencia');
        });
    }
};
