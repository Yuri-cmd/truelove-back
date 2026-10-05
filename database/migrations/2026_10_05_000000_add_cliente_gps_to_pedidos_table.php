<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación GPS real del teléfono del cliente al hacer el pedido. Es
     * independiente de latitud/longitud, que es el punto de entrega elegido.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'cliente_latitud')) {
                $table->decimal('cliente_latitud', 10, 7)->nullable()->after('longitud');
            }
            if (!Schema::hasColumn('pedidos', 'cliente_longitud')) {
                $table->decimal('cliente_longitud', 10, 7)->nullable()->after('cliente_latitud');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['cliente_latitud', 'cliente_longitud'] as $columna) {
                if (Schema::hasColumn('pedidos', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
