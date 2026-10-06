<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El motorizado también puede solicitar la cancelación de un pedido que no pudo
     * entregar (ej. el cliente no contesta). Si marca que fue culpa del cliente, el
     * admin ve sugerida una deuda al aprobar la solicitud.
     */
    public function up(): void
    {
        Schema::table('pedido_cancelacion_solicitudes', function (Blueprint $table) {
            $table->unsignedBigInteger('solicitado_por_motorizado_id')->nullable()->after('solicitado_por_socio_id');
            $table->boolean('culpa_cliente')->default(false)->after('solicitado_por_motorizado_id');
            $table->text('detalle')->nullable()->after('culpa_cliente');
            $table->unsignedBigInteger('deuda_id')->nullable()->after('detalle');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_cancelacion_solicitudes', function (Blueprint $table) {
            $table->dropColumn(['solicitado_por_motorizado_id', 'culpa_cliente', 'detalle', 'deuda_id']);
        });
    }
};
