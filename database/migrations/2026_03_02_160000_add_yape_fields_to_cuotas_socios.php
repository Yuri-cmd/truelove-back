<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas_socios', function (Blueprint $table) {
            $table->string('numero_yape', 20)->nullable()->after('metodos_pago_disponibles');
            $table->string('titular_yape', 100)->nullable()->after('numero_yape');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas_socios', function (Blueprint $table) {
            $table->dropColumn(['numero_yape', 'titular_yape']);
        });
    }
};
