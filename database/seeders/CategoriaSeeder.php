<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    public function run()
    {
        $categorias = [
            // Restaurant categories
            ['nombre' => 'Comida Rápida', 'tipo_negocio_id' => 1],
            ['nombre' => 'Restaurante Formal', 'tipo_negocio_id' => 1],
            // Retail Store categories
            ['nombre' => 'Ropa', 'tipo_negocio_id' => 2],
            ['nombre' => 'Electrónicos', 'tipo_negocio_id' => 2],
            // Service categories
            ['nombre' => 'Limpieza', 'tipo_negocio_id' => 3],
            ['nombre' => 'Mantenimiento', 'tipo_negocio_id' => 3],
            // Hotel categories
            ['nombre' => 'Hotel 5 Estrellas', 'tipo_negocio_id' => 4],
            ['nombre' => 'Hostal', 'tipo_negocio_id' => 4],
        ];

        foreach ($categorias as $categoria) {
            DB::table('categorias')->insert([
                'nombre' => $categoria['nombre'],
                'slug' => Str::slug($categoria['nombre']),
                'tipo_negocio_id' => $categoria['tipo_negocio_id'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}