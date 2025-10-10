<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PeriodoCuotaService;

class MarcarPeriodosVencidos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'periodos:marcar-vencidos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marca períodos de cuotas como vencidos si ya pasó su fecha de vencimiento';

    protected $periodoCuotaService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(PeriodoCuotaService $periodoCuotaService)
    {
        parent::__construct();
        $this->periodoCuotaService = $periodoCuotaService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Marcando períodos vencidos...');

        $cantidadActualizada = $this->periodoCuotaService->marcarPeriodosVencidos();

        $this->info("✅ {$cantidadActualizada} período(s) marcado(s) como vencido(s)");

        return 0;
    }
}
