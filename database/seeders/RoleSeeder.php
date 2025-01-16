<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // En lugar de truncar, usamos updateOrCreate para cada rol
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador del sistema'],
            ['name' => 'negocio', 'description' => 'Socio o dueño de negocio'],
            ['name' => 'motorizado', 'description' => 'Repartidor o motorizado']
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}