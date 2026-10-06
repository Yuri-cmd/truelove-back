<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Múltiples direcciones por cliente.
     *
     * - clientes_direcciones.activa: la dirección seleccionada para los pedidos. Las apps
     *   antiguas, que solo conocen una dirección, siguen leyendo y editando la activa.
     * - clientes_direcciones.version: sube cuando la ubicación de la dirección cambia de
     *   verdad (otro texto u otro punto). Las notas de la casa se ligan a (dirección, versión).
     * - pedidos.id_direccion / direccion_version: dirección usada al crear el pedido. Solo
     *   las apps nuevas la envían; si es NULL se usa la lógica anterior (por ubicación y texto).
     * - entrega_notas.id_direccion / direccion_version: igual, para ligar la nota a la dirección.
     */
    public function up(): void
    {
        Schema::table('clientes_direcciones', function (Blueprint $table) {
            $table->boolean('activa')->default(false)->after('alias');
            $table->unsignedInteger('version')->default(1)->after('activa');
            $table->index(['id_cliente', 'activa']);
        });

        // Hasta hoy cada cliente tiene una sola dirección: esa pasa a ser la activa.
        DB::table('clientes_direcciones')->update(['activa' => true]);

        Schema::table('pedidos', function (Blueprint $table) {
            $table->unsignedBigInteger('id_direccion')->nullable()->after('longitud');
            $table->unsignedInteger('direccion_version')->nullable()->after('id_direccion');
        });

        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_direccion')->nullable()->after('cliente_id')->index();
            $table->unsignedInteger('direccion_version')->nullable()->after('id_direccion');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->dropColumn(['id_direccion', 'direccion_version']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['id_direccion', 'direccion_version']);
        });

        Schema::table('clientes_direcciones', function (Blueprint $table) {
            $table->dropIndex(['id_cliente', 'activa']);
            $table->dropColumn(['activa', 'version']);
        });
    }
};
