<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Banco;

class BancosSeeder extends Seeder
{
    public function run()
    {
        $bancos = [
            ['nombre' => 'BANCO DE CREDITO DEL PERU'],
            ['nombre' => 'INTERBANK'],
            ['nombre' => 'BBVA'],
        ];

        foreach ($bancos as $banco) {
            Banco::create($banco);
        }
    }
}