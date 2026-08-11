<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            // 'pantalla' | 'restaurante' | 'categoria' | null (sin acción al presionar)
            $table->string('tipo_destino')->nullable()->after('estado');
            // Identificador de pantalla fija cuando tipo_destino = 'pantalla' (ej. 'cupones', 'perfil', 'home')
            $table->string('pantalla')->nullable()->after('tipo_destino');
            // business_registration_id (restaurante) o tipos_negocios.id (categoria)
            $table->unsignedBigInteger('destino_id')->nullable()->after('pantalla');
        });
    }

    public function down(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            $table->dropColumn(['tipo_destino', 'pantalla', 'destino_id']);
        });
    }
};
