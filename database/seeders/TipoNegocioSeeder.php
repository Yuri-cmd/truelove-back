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
            ['nombre' => 'Comida Rápida', 'slug' => 'comida-rapida'],
            ['nombre' => 'Librería', 'slug' => 'libreria'],
            ['nombre' => 'Cosmetología', 'slug' => 'cosmetologia'],
            ['nombre' => 'Bisutería', 'slug' => 'bisuteria'],
            ['nombre' => 'Detalles de amor', 'slug' => 'detalles-de-amor'],
            ['nombre' => 'Licorería', 'slug' => 'licoreria'],
            ['nombre' => 'Farmacia', 'slug' => 'farmacia'],
            ['nombre' => 'Chifa', 'slug' => 'chifa'],
            ['nombre' => 'Pollería', 'slug' => 'polleria'],
            ['nombre' => 'Hamburguesería', 'slug' => 'hamburgueseria'],
            ['nombre' => 'Broastería', 'slug' => 'broasteria'],
            ['nombre' => 'Shawarma', 'slug' => 'shawarma'],
            ['nombre' => 'Cevichería', 'slug' => 'cevichera'],
            ['nombre' => 'Juguería', 'slug' => 'jugueria'],
            ['nombre' => 'Comidas Criollas', 'slug' => 'comidas-criollas'],
            ['nombre' => 'Dulcería', 'slug' => 'dulceria'],
            ['nombre' => 'Cafetería', 'slug' => 'cafeteria'],
            ['nombre' => 'Pizzería', 'slug' => 'pizzeria'],
            ['nombre' => 'Heladería', 'slug' => 'heladeria'],
            ['nombre' => 'Comidas de la Selva', 'slug' => 'comidas-de-la-selva'],
            ['nombre' => 'Comidas Mexicanas', 'slug' => 'comidas-mexicanas'],
            ['nombre' => 'Comidas Venezolanas', 'slug' => 'comidas-venezolanas'],
            ['nombre' => 'Anticuchería', 'slug' => 'anticucheria'],
            ['nombre' => 'Crepería', 'slug' => 'creperia'],
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
