<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoritos_locales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('business_registration_id')->constrained('business_registrations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cliente_id', 'business_registration_id'], 'favoritos_locales_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoritos_locales');
    }
};
