<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->time('hora_inicio')->nullable()->after('nombre');
            $table->time('hora_fin')->nullable()->after('hora_inicio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin']);
        });
    }
};
