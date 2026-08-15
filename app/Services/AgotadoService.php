<?php

namespace App\Services;

use App\Models\Adicional;
use App\Models\Menu;
use Carbon\Carbon;
use InvalidArgumentException;

class AgotadoService
{
    /**
     * Duraciones permitidas para marcar un producto/opción como agotado.
     */
    public const DURACIONES = ['medianoche', '1_dia', '2_dias', '3_dias', '1_semana'];

    /**
     * Calcula la fecha/hora hasta la que un producto queda agotado.
     */
    public function calcularAgotadoHasta(string $duracion): Carbon
    {
        return match ($duracion) {
            'medianoche' => Carbon::now()->endOfDay(),
            '1_dia' => Carbon::now()->addDay(),
            '2_dias' => Carbon::now()->addDays(2),
            '3_dias' => Carbon::now()->addDays(3),
            '1_semana' => Carbon::now()->addWeek(),
            default => throw new InvalidArgumentException("Duración inválida: {$duracion}"),
        };
    }

    /**
     * Marca varios productos del menú como agotados hasta la fecha calculada.
     */
    public function marcarMenusAgotados(array $ids, string $duracion): int
    {
        $agotadoHasta = $this->calcularAgotadoHasta($duracion);

        return Menu::whereIn('id', $ids)->update([
            'status' => 'out-of-stock',
            'agotado_hasta' => $agotadoHasta,
        ]);
    }

    public function marcarMenusDisponibles(array $ids): int
    {
        return Menu::whereIn('id', $ids)->update([
            'status' => 'active',
            'agotado_hasta' => null,
        ]);
    }

    /**
     * Marca varias opciones/adicionales como agotadas hasta la fecha calculada.
     */
    public function marcarAdicionalesAgotados(array $ids, string $duracion): int
    {
        $agotadoHasta = $this->calcularAgotadoHasta($duracion);

        return Adicional::whereIn('id', $ids)->update([
            'status' => 'out-of-stock',
            'agotado_hasta' => $agotadoHasta,
        ]);
    }

    public function marcarAdicionalesDisponibles(array $ids): int
    {
        return Adicional::whereIn('id', $ids)->update([
            'status' => 'active',
            'agotado_hasta' => null,
        ]);
    }

    /**
     * Reactiva productos y opciones cuyo tiempo de agotado ya venció.
     * Se ejecuta periódicamente desde el scheduler.
     */
    public function reactivarVencidos(): array
    {
        $menusReactivados = Menu::where('status', 'out-of-stock')
            ->whereNotNull('agotado_hasta')
            ->where('agotado_hasta', '<=', Carbon::now())
            ->update(['status' => 'active', 'agotado_hasta' => null]);

        $adicionalesReactivados = Adicional::where('status', 'out-of-stock')
            ->whereNotNull('agotado_hasta')
            ->where('agotado_hasta', '<=', Carbon::now())
            ->update(['status' => 'active', 'agotado_hasta' => null]);

        return [
            'menus' => $menusReactivados,
            'adicionales' => $adicionalesReactivados,
        ];
    }
}
