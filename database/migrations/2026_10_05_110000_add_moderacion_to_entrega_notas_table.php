<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisión por el admin: las notas se ven de inmediato ('pendiente') para que
     * sirvan al siguiente repartidor, y el admin las aprueba o rechaza después.
     * Las rechazadas dejan de mostrarse a los repartidores.
     */
    public function up(): void
    {
        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->string('estado', 20)->default('pendiente')->after('foto_path')->index();
            $table->unsignedBigInteger('revisada_por')->nullable()->after('estado');
            $table->timestamp('revisada_at')->nullable()->after('revisada_por');
            $table->string('motivo_rechazo')->nullable()->after('revisada_at');
        });
    }

    public function down(): void
    {
        Schema::table('entrega_notas', function (Blueprint $table) {
            $table->dropColumn(['estado', 'revisada_por', 'revisada_at', 'motivo_rechazo']);
        });
    }
};
