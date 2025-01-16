<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Primero creamos los roles básicos
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador del sistema'],
            ['name' => 'negocio', 'description' => 'Socio o dueño de negocio'],
            ['name' => 'motorizado', 'description' => 'Repartidor o motorizado']
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }

        // Obtenemos el rol de admin
        $adminRole = Role::where('name', 'admin')->first();

        // Creamos el usuario administrador
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'usuario' => 'testuser',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
                'estado' => true,
                'role_id' => $adminRole->id // Asignamos el rol de admin
            ]
        );

        // Ejecutamos los demás seeders
        $this->call([
            TipoNegocioSeeder::class,
            CategoriaSeeder::class,
            CiudadesYDistritosPeruSeeder::class,
            TiposCuentaBancariaSeeder::class,
            BancosSeeder::class,
        ]);
    }
}