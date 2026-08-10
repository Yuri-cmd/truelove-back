<?php

namespace App\Services;

use App\Models\HorarioAsignacion;
use App\Models\HorarioGrupo;
use App\Models\Pedido;
use App\Models\RepartoRegistro;
use Carbon\Carbon;

class HorarioService
{
    /**
     * Determina si un motorizado puede trabajar ahora mismo: dentro de su
     * horario asignado (individual o grupal), o con un pedido activo en
     * curso (para no dejarlo colgado a mitad de una entrega).
     *
     * Extraído de BikerController::condiciones() para poder reutilizarlo
     * también al validar la toma de un nuevo pedido (PedidoController).
     */
    public function puedeTrabajar(int $motorizadoId): array
    {
        $motorizado = RepartoRegistro::find($motorizadoId);
        if (!$motorizado) {
            return ['puede_trabajar' => false, 'mensaje' => 'Motorizado no encontrado'];
        }

        $horarioIndividual = HorarioGrupo::where('motorizado_individual_id', $motorizadoId)
            ->where('tipo', 'individual')
            ->with('bloques')
            ->first();

        $horarioGrupal = HorarioAsignacion::where('motorizado_id', $motorizadoId)
            ->with('grupo.bloques')
            ->first();

        $bloques = collect();
        if ($horarioIndividual && $horarioIndividual->bloques->count() > 0) {
            $bloques = $horarioIndividual->bloques;
        } elseif ($horarioGrupal && $horarioGrupal->grupo && $horarioGrupal->grupo->bloques->count() > 0) {
            $bloques = $horarioGrupal->grupo->bloques;
        }

        $pedidosActivos = Pedido::where('id_motorizado', $motorizadoId)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->whereHas('trackings', function ($query) {
                $query->whereIn('estado', [1, 2, 3, 4, 5, 6, 7])
                    ->whereRaw('id = (SELECT MAX(id) FROM pedido_trackings WHERE pedido_id = pedidos.id)');
            })
            ->count();
        $tienePedidoActivo = $pedidosActivos > 0;

        if ($bloques->isEmpty()) {
            if ($tienePedidoActivo) {
                return ['puede_trabajar' => true, 'mensaje' => 'Fuera de horario pero con pedido activo'];
            }
            return ['puede_trabajar' => false, 'mensaje' => 'No hay horario asignado'];
        }

        $now = Carbon::now('America/Lima');
        $diaActual = $this->normalizar(strtolower($now->locale('es')->dayName));
        $horaActual = $now->format('H:i');

        $dentroDeHorario = false;

        foreach ($bloques as $bloque) {
            $diasBloque = is_string($bloque->dia_semana)
                ? json_decode($bloque->dia_semana, true)
                : $bloque->dia_semana;

            if (!is_array($diasBloque)) {
                continue;
            }
            $diasBloque = array_map(fn ($d) => $this->normalizar(strtolower($d)), $diasBloque);

            if (!in_array($diaActual, $diasBloque)) {
                continue;
            }

            $horaInicio = Carbon::parse($bloque->hora_inicio)->format('H:i');
            $horaFin = Carbon::parse($bloque->hora_fin)->format('H:i');

            $horaActualObj = Carbon::createFromFormat('H:i', $horaActual);
            $horaInicioObj = Carbon::createFromFormat('H:i', $horaInicio);
            $horaFinObj = Carbon::createFromFormat('H:i', $horaFin);

            if ($horaInicioObj <= $horaFinObj) {
                if ($horaActualObj >= $horaInicioObj && $horaActualObj <= $horaFinObj) {
                    $dentroDeHorario = true;
                    break;
                }
            } else {
                // Bloque que cruza medianoche, ej: 20:00 a 02:00
                if ($horaActualObj >= $horaInicioObj || $horaActualObj <= $horaFinObj) {
                    $dentroDeHorario = true;
                    break;
                }
            }
        }

        $puedeTrabajar = $dentroDeHorario || $tienePedidoActivo;
        $mensaje = $dentroDeHorario
            ? 'Puede trabajar'
            : ($tienePedidoActivo ? 'Fuera de horario pero con pedido activo' : 'Se encuentra fuera del rango del horario');

        return ['puede_trabajar' => $puedeTrabajar, 'mensaje' => $mensaje];
    }

    private function normalizar(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
        ]);
    }
}
