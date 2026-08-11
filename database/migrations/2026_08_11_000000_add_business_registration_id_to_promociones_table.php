<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            // null = promoción global creada por el admin de la plataforma.
            // con valor = promoción propia de ese local.
            $table->foreignId('business_registration_id')
                ->nullable()
                ->after('id')
                ->constrained('business_registrations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promociones', function (Blueprint $table) {
            $table->dropForeign(['business_registration_id']);
            $table->dropColumn('business_registration_id');
        });
    }
};
