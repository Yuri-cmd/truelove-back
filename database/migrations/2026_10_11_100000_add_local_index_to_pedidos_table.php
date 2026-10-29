<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDICE = 'pedidos_id_local_created_at_index';

    /**
     * El listado de locales y el detalle calculan la calificación y el tiempo
     * de preparación promedio filtrando pedidos por id_local (y por fecha en
     * el caso del tiempo), así que esa combinación necesita índice.
     * ratings.id_pedido ya lo tiene por su llave foránea.
     */
    public function up(): void
    {
        if ($this->indiceExiste()) {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['id_local', 'created_at'], self::INDICE);
        });
    }

    public function down(): void
    {
        if (!$this->indiceExiste()) {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(self::INDICE);
        });
    }

    private function indiceExiste(): bool
    {
        foreach (Schema::getIndexes('pedidos') as $indice) {
            if ($indice['name'] === self::INDICE) {
                return true;
            }
        }

        return false;
    }
};
