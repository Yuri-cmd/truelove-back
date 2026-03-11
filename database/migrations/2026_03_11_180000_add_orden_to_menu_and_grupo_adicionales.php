<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Orden para productos del menú
        Schema::table('menu', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('status');
        });

        // Asignar orden inicial basado en ID
        DB::statement('UPDATE menu SET orden = id');

        // Orden para grupos de adicionales a nivel empresa
        Schema::table('grupo_adicionales', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('estado');
        });

        // Asignar orden inicial basado en ID
        DB::statement('UPDATE grupo_adicionales SET orden = id');
    }

    public function down(): void
    {
        Schema::table('menu', function (Blueprint $table) {
            $table->dropColumn('orden');
        });

        Schema::table('grupo_adicionales', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
