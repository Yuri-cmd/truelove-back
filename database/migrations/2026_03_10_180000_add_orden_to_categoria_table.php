<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('estado');
        });

        // Asignar orden inicial basado en ID (las que ya existen)
        DB::statement('UPDATE categoria SET orden = id');
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
