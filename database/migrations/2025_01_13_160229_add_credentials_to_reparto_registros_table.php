<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('reparto_registros', function (Blueprint $table) {
        $table->string('username')->nullable();
        $table->string('password')->nullable();
    });
}

public function down()
{
    Schema::table('reparto_registros', function (Blueprint $table) {
        $table->dropColumn(['username', 'password']);
    });
}
};
