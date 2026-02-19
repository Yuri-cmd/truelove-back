<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('app_versions')->updateOrInsert(
            ['app_name' => 'cliente'],
            [
                'min_version' => '0.1.25',
                'latest_version' => '0.1.25',
                'force_update' => true,
                'url_android' => 'https://play.google.com/store/apps/details?id=com.truelove.trueloveclient',
                'url_ios' => 'https://apps.apple.com/app/id-truelove-client',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('app_versions')->updateOrInsert(
            ['app_name' => 'socio'],
            [
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'force_update' => false,
                'url_android' => 'https://play.google.com/store/apps/details?id=com.truelove.socio',
                'url_ios' => 'https://apps.apple.com/app/id-truelove-socio',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('app_versions')->updateOrInsert(
            ['app_name' => 'motorizado'],
            [
                'min_version' => '1.0.0',
                'latest_version' => '1.0.0',
                'force_update' => false,
                'url_android' => 'https://play.google.com/store/apps/details?id=com.truelove.motorizado',
                'url_ios' => 'https://apps.apple.com/app/id-truelove-motorizado',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
