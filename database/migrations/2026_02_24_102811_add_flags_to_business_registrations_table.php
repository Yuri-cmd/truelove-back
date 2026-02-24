<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_registrations', function (Blueprint $table) {
            $table->boolean('omitir_pago_adelantado')->default(false)->after('activo');
            $table->boolean('omitir_validacion_100')->default(false)->after('omitir_pago_adelantado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_registrations', function (Blueprint $table) {
            $table->dropColumn(['omitir_pago_adelantado', 'omitir_validacion_100']);
        });
    }
};
