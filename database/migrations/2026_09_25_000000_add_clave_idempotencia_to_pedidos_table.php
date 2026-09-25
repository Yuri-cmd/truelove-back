<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que la app del cliente reintente confirmar un pedido tras un corte de
     * conexión sin crear uno duplicado: reenvía la misma clave y el backend devuelve
     * el pedido ya creado en vez de crear otro.
     */
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'clave_idempotencia')) {
                $table->string('clave_idempotencia')->nullable()->unique()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'clave_idempotencia')) {
                $table->dropColumn('clave_idempotencia');
            }
        });
    }
};
