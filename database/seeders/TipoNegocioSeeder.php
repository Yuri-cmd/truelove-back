<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TipoNegocioSeeder extends Seeder
{
    public function run()
    {
        $tipos = [
            ['nombre' => 'Restaurante', 'slug' => 'restaurant'],
            ['nombre' => 'Tienda Minorista', 'slug' => 'retail-store'],
            ['nombre' => 'Servicios', 'slug' => 'service'],
            ['nombre' => 'Hotel', 'slug' => 'hotel'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('tipos_negocios')->insert([
                'nombre' => $tipo['nombre'],
                'slug' => $tipo['slug'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}