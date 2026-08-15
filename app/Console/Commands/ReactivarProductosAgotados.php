<?php

namespace App\Console\Commands;

use App\Services\AgotadoService;
use Illuminate\Console\Command;

class ReactivarProductosAgotados extends Command
{
    protected $signature = 'agotados:reactivar';

    protected $description = 'Reactiva productos y opciones marcados como agotados cuya fecha límite ya venció';

    public function handle(AgotadoService $agotadoService)
    {
        $resultado = $agotadoService->reactivarVencidos();

        $this->info("✅ {$resultado['menus']} producto(s) y {$resultado['adicionales']} opción(es) reactivados");

        return 0;
    }
}
