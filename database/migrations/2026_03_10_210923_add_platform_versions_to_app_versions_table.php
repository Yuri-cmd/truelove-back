<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->string('min_version_android')->nullable()->after('min_version');
            $table->string('min_version_ios')->nullable()->after('min_version_android');
            $table->string('latest_version_android')->nullable()->after('latest_version');
            $table->string('latest_version_ios')->nullable()->after('latest_version_android');
            $table->boolean('force_update_android')->default(true)->after('force_update');
            $table->boolean('force_update_ios')->default(true)->after('force_update_android');
        });

        // Initialize with default values from main columns
        DB::table('app_versions')->update([
            'min_version_android' => DB::raw('min_version'),
            'min_version_ios' => DB::raw('min_version'),
            'latest_version_android' => DB::raw('latest_version'),
            'latest_version_ios' => DB::raw('latest_version'),
            'force_update_android' => DB::raw('force_update'),
            'force_update_ios' => DB::raw('force_update'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn([
                'min_version_android', 'min_version_ios',
                'latest_version_android', 'latest_version_ios',
                'force_update_android', 'force_update_ios'
            ]);
        });
    }
};
