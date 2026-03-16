<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\EnviarMensajeAutomaticoDriver;
use App\Console\Commands\MarcarPeriodosVencidos;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Schedule::command(EnviarMensajeAutomaticoDriver::class)
    ->everyMinute();

Schedule::command(MarcarPeriodosVencidos::class)
    ->dailyAt('00:05');
