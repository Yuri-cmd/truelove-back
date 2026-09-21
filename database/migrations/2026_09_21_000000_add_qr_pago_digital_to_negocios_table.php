<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            if (!Schema::hasColumn('negocios', 'qr_pago_digital')) {
                $table->string('qr_pago_digital')->nullable()->after('nombre_titular_pago_digital');
            }
        });
    }

    public function down(): void
    {
        Schema::table('negocios', function (Blueprint $table) {
            if (Schema::hasColumn('negocios', 'qr_pago_digital')) {
                $table->dropColumn('qr_pago_digital');
            }
        });
    }
};
