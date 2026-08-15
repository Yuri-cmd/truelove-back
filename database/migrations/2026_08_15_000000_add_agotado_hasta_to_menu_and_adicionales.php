<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->dateTime('agotado_hasta')->nullable()->after('status');
        });

        Schema::table('adicionales', function (Blueprint $table) {
            $table->dateTime('agotado_hasta')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->dropColumn('agotado_hasta');
        });

        Schema::table('adicionales', function (Blueprint $table) {
            $table->dropColumn('agotado_hasta');
        });
    }
};
