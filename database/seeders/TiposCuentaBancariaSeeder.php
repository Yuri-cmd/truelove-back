<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoCuentaBancaria;

class TiposCuentaBancariaSeeder extends Seeder
{
    public function run()
    {
        $tiposCuenta = [
            ['nombre' => 'Cuenta de Ahorro'],
            ['nombre' => 'Cuenta Corriente'],
        ];

        foreach ($tiposCuenta as $tipo) {
            TipoCuentaBancaria::create($tipo);
        }
    }
}

