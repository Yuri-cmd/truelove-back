<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastName');
            $table->string('businessType');
            $table->string('phone');
            $table->string('email');
            $table->string('verification_code')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->smallInteger('estado')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_registrations');
    }
};