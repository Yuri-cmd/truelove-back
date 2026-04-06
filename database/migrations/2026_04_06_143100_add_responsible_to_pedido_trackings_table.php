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
        Schema::table('pedido_trackings', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('estado');
            $table->string('user_type')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido_trackings', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'user_type']);
        });
    }
};
