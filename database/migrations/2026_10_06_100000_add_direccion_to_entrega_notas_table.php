<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dirección escrita del pedido en el que se dejó la nota. Junto con la distancia permite
     * distinguir la casa de al lado: si el cliente cambia de dirección, la nota no se muestra.
     */
    public function up(): void
    {
        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('longitud');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->dropColumn('direccion');
        });
    }
};
