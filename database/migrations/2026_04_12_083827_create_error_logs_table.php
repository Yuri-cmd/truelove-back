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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('app_name');
            $table->text('error_message');
            $table->longText('stack_trace')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('device_info')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->json('request_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
