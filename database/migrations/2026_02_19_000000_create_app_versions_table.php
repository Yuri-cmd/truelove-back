<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->unique(); // 'cliente', 'socio', 'motorizado'
            $table->string('min_version');
            $table->string('latest_version');
            $table->boolean('force_update')->default(false);
            $table->string('url_android')->nullable();
            $table->string('url_ios')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_versions');
    }
};
