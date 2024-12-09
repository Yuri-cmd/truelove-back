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
            // Comida Rápida
            ['nombre' => 'Hamburguesas', 'tipo_negocio_id' => 1],
            ['nombre' => 'Papas Fritas', 'tipo_negocio_id' => 1],
            ['nombre' => 'Perros Calientes', 'tipo_negocio_id' => 1],
            // Librería
            ['nombre' => 'Libros', 'tipo_negocio_id' => 2],
            ['nombre' => 'Revistas', 'tipo_negocio_id' => 2],
            ['nombre' => 'Material Escolar', 'tipo_negocio_id' => 2],
            // Cosmetología
            ['nombre' => 'Maquillaje', 'tipo_negocio_id' => 3],
            ['nombre' => 'Cuidado Facial', 'tipo_negocio_id' => 3],
            ['nombre' => 'Cuidado Capilar', 'tipo_negocio_id' => 3],
            // Bisutería
            ['nombre' => 'Collares', 'tipo_negocio_id' => 4],
            ['nombre' => 'Pulseras', 'tipo_negocio_id' => 4],
            ['nombre' => 'Anillos', 'tipo_negocio_id' => 4],
            // Detalles de amor
            ['nombre' => 'Regalos Especiales', 'tipo_negocio_id' => 5],
            ['nombre' => 'Ramos de Flores', 'tipo_negocio_id' => 5],
            // Licorería
            ['nombre' => 'Vinos', 'tipo_negocio_id' => 6],
            ['nombre' => 'Cervezas', 'tipo_negocio_id' => 6],
            ['nombre' => 'Licores', 'tipo_negocio_id' => 6],
            // Farmacia
            ['nombre' => 'Medicamentos', 'tipo_negocio_id' => 7],
            ['nombre' => 'Suplementos', 'tipo_negocio_id' => 7],
            ['nombre' => 'Cuidado Personal', 'tipo_negocio_id' => 7],
            // Chifa
            ['nombre' => 'Arroz Chaufa', 'tipo_negocio_id' => 8],
            ['nombre' => 'Pollo a la Brasa', 'tipo_negocio_id' => 8],
            // Pollería
            ['nombre' => 'Pollo Frito', 'tipo_negocio_id' => 9],
            ['nombre' => 'Pollo a la Parrilla', 'tipo_negocio_id' => 9],
            // Hamburguesería
            ['nombre' => 'Hamburguesas Clásicas', 'tipo_negocio_id' => 10],
            ['nombre' => 'Hamburguesas Gourmet', 'tipo_negocio_id' => 10],
            // Broastería
            ['nombre' => 'Pollo Broaster', 'tipo_negocio_id' => 11],
            ['nombre' => 'Pechugas Broaster', 'tipo_negocio_id' => 11],
            // Shawarma
            ['nombre' => 'Shawarma de Pollo', 'tipo_negocio_id' => 12],
            ['nombre' => 'Shawarma de Res', 'tipo_negocio_id' => 12],
            // Cevichería
            ['nombre' => 'Ceviche', 'tipo_negocio_id' => 13],
            ['nombre' => 'Piqueos', 'tipo_negocio_id' => 13],
            // Juguería
            ['nombre' => 'Jugos Naturales', 'tipo_negocio_id' => 14],
            ['nombre' => 'Smoothies', 'tipo_negocio_id' => 14],
            // Comidas Criollas
            ['nombre' => 'Arroz con Pollo', 'tipo_negocio_id' => 15],
            ['nombre' => 'Lomo Saltado', 'tipo_negocio_id' => 15],
            // Dulcería
            ['nombre' => 'Dulces Caseros', 'tipo_negocio_id' => 16],
            ['nombre' => 'Galletas', 'tipo_negocio_id' => 16],
            // Cafetería
            ['nombre' => 'Café Espresso', 'tipo_negocio_id' => 17],
            ['nombre' => 'Café Latte', 'tipo_negocio_id' => 17],
            // Pizzería
            ['nombre' => 'Pizzas Clásicas', 'tipo_negocio_id' => 18],
            ['nombre' => 'Pizzas Gourmet', 'tipo_negocio_id' => 18],
            // Heladería
            ['nombre' => 'Helados Artesanales', 'tipo_negocio_id' => 19],
            ['nombre' => 'Paletas de Fruta', 'tipo_negocio_id' => 19],
            // Comidas de la Selva
            ['nombre' => 'Juanes', 'tipo_negocio_id' => 20],
            ['nombre' => 'Tacacho con Cecina', 'tipo_negocio_id' => 20],
            // Comidas Mexicanas
            ['nombre' => 'Tacos', 'tipo_negocio_id' => 21],
            ['nombre' => 'Burritos', 'tipo_negocio_id' => 21],
            // Comidas Venezolanas
            ['nombre' => 'Arepas', 'tipo_negocio_id' => 22],
            ['nombre' => 'Pabellón Criollo', 'tipo_negocio_id' => 22],
            // Anticuchería
            ['nombre' => 'Anticuchos', 'tipo_negocio_id' => 23],
            ['nombre' => 'Papa a la Huancaína', 'tipo_negocio_id' => 23],
            // Crepería
            ['nombre' => 'Crepes Dulces', 'tipo_negocio_id' => 24],
            ['nombre' => 'Crepes Salados', 'tipo_negocio_id' => 24],
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
