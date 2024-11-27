<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ciudad;
use App\Models\Distrito;

class CiudadesYDistritosPeruSeeder extends Seeder
{
    public function run()
    {
        $ciudadesYDistritos = [
            'Lima' => [
                'Lima', 'Ancón', 'Ate', 'Barranco', 'Breña', 'Carabayllo', 'Chaclacayo', 'Chorrillos', 
                'Cieneguilla', 'Comas', 'El Agustino', 'Independencia', 'Jesús María', 'La Molina', 
                'La Victoria', 'Lince', 'Los Olivos', 'Lurigancho', 'Lurín', 'Magdalena del Mar', 
                'Miraflores', 'Pachacámac', 'Pucusana', 'Pueblo Libre', 'Puente Piedra', 'Punta Hermosa', 
                'Punta Negra', 'Rímac', 'San Bartolo', 'San Borja', 'San Isidro', 'San Juan de Lurigancho', 
                'San Juan de Miraflores', 'San Luis', 'San Martín de Porres', 'San Miguel', 'Santa Anita', 
                'Santa María del Mar', 'Santa Rosa', 'Santiago de Surco', 'Surquillo', 'Villa El Salvador', 
                'Villa María del Triunfo'
            ],
            'Arequipa' => [
                'Alto Selva Alegre', 'Arequipa', 'Cayma', 'Cerro Colorado', 'Characato', 'Chiguata', 
                'Jacobo Hunter', 'José Luis Bustamante y Rivero', 'La Joya', 'Mariano Melgar', 'Miraflores', 
                'Mollebaya', 'Paucarpata', 'Pocsi', 'Polobaya', 'Quequeña', 'Sabandia', 'Sachaca', 
                'San Juan de Siguas', 'San Juan de Tarucani', 'Santa Isabel de Siguas', 'Santa Rita de Siguas', 
                'Socabaya', 'Tiabaya', 'Uchumayo', 'Vitor', 'Yanahuara', 'Yarabamba', 'Yura'
            ],
            'Trujillo' => [
                'Trujillo', 'El Porvenir', 'Florencia de Mora', 'Huanchaco', 'La Esperanza', 'Laredo', 
                'Moche', 'Poroto', 'Salaverry', 'Simbal', 'Víctor Larco Herrera'
            ],
            'Chiclayo' => [
                'Chiclayo', 'Chongoyape', 'Eten', 'Eten Puerto', 'José Leonardo Ortiz', 'La Victoria', 
                'Lagunas', 'Monsefú', 'Nueva Arica', 'Oyotún', 'Pátapo', 'Picsi', 'Pimentel', 'Pomalca', 
                'Pucalá', 'Reque', 'Santa Rosa', 'Saña', 'Cayaltí', 'Zaña'
            ],
            'Cusco' => [
                'Cusco', 'Ccorca', 'Poroy', 'San Jerónimo', 'San Sebastian', 'Santiago', 'Saylla', 'Wanchaq'
            ],
            'Piura' => [
                'Piura', 'Castilla', 'Catacaos', 'Cura Mori', 'El Tallán', 'La Arena', 'La Unión', 
                'Las Lomas', 'Tambo Grande', 'Veintiséis de Octubre'
            ],
            'Iquitos' => [
                'Iquitos', 'Alto Nanay', 'Fernando Lores', 'Indiana', 'Las Amazonas', 'Mazan', 'Napo', 
                'Punchana', 'Torres Causana', 'Belén', 'San Juan Bautista'
            ]
        ];

        foreach ($ciudadesYDistritos as $nombreCiudad => $distritos) {
            $ciudad = Ciudad::create(['nombre' => $nombreCiudad]);
            foreach ($distritos as $nombreDistrito) {
                Distrito::create([
                    'nombre' => $nombreDistrito,
                    'ciudad_id' => $ciudad->id
                ]);
            }
        }
    }
}

