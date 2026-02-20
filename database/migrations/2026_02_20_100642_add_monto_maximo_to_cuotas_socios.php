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
        Schema::table('cuotas_socios', function (Blueprint $table) {
            $table->decimal('monto_maximo', 10, 2)->nullable()->after('monto_minimo')
                  ->comment('Tope máximo de comisión a cobrar por período (solo aplica para tipo porcentaje)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuotas_socios', function (Blueprint $table) {
            $table->dropColumn('monto_maximo');
        });
    }
};
