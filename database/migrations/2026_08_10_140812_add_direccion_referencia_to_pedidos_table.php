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
        Schema::table('pedidos', function (Blueprint $table) {
            // Verificado con hasColumn: en el entorno local estas columnas ya
            // existían (agregadas manualmente sin migración, sin registrar);
            // en producción no. Este chequeo evita fallar en ninguno de los
            // dos casos.
            if (!Schema::hasColumn('pedidos', 'direccion')) {
                $table->string('direccion')->nullable()->after('longitud');
            }
            if (!Schema::hasColumn('pedidos', 'referencia')) {
                $table->string('referencia')->nullable()->after('direccion');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'direccion')) {
                $table->dropColumn('direccion');
            }
            if (Schema::hasColumn('pedidos', 'referencia')) {
                $table->dropColumn('referencia');
            }
        });
    }
};
