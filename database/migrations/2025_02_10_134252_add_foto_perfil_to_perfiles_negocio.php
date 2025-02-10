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
        Schema::table('perfiles_negocio', function (Blueprint $table) {
            // Agregar columna foto_perfil
            $table->string('foto_perfil')->nullable()->after('ruta_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('perfiles_negocio', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });
    }
};
