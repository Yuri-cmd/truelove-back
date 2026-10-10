<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos_cuotas_socios', function (Blueprint $table) {
            // Pago adelantado: hora exacta hasta la que cuentan las ventas de este período
            $table->dateTime('ventas_hasta')->nullable();
            // Hora exacta desde la que cuentan las ventas (período siguiente a uno pagado por adelantado)
            $table->dateTime('ventas_desde')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('periodos_cuotas_socios', function (Blueprint $table) {
            $table->dropColumn(['ventas_hasta', 'ventas_desde']);
        });
    }
};
