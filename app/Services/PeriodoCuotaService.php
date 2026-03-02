<?php

namespace App\Services;

use App\Models\PeriodoCuotaSocio;
use App\Models\CuotaSocio;
use App\Models\BusinessRegistration;
use Carbon\Carbon;

class PeriodoCuotaService
{
    /**
     * Genera períodos automáticos cuando se asigna una cuota a un socio
     *
     * @param int $socioId
     * @param int $cuotaId
     * @param int $cantidadPeriodos Cantidad de períodos a generar (por defecto 12)
     * @param string|null $fechaInicioPersonalizada Fecha de inicio personalizada (opcional)
     * @return array Períodos generados
     */
    public function generarPeriodosParaSocio($socioId, $cuotaId, $cantidadPeriodos = 12, $fechaInicioPersonalizada = null)
    {
        $cuota = CuotaSocio::findOrFail($cuotaId);
        $socio = BusinessRegistration::findOrFail($socioId);

        // Eliminar períodos antiguos pendientes/en_revision (no los pagados)
        PeriodoCuotaSocio::where('socio_id', $socioId)
            ->whereIn('estado', ['pendiente', 'en_revision'])
            ->delete();

        // Usar la fecha personalizada si se proporciona, sino usar la fecha actual
        $fechaInicio = $fechaInicioPersonalizada
            ? Carbon::parse($fechaInicioPersonalizada)->startOfDay()
            : Carbon::now()->startOfDay();
        $periodos = [];

        for ($i = 0; $i < $cantidadPeriodos; $i++) {
            $periodoInicio = $i === 0 ? $fechaInicio->copy() : $fechaFin->copy()->addDay();

            $fechaFin = match($cuota->periodicidad) {
                'diario' => $periodoInicio->copy()->endOfDay(),
                'semanal' => $periodoInicio->copy()->addWeek()->subDay(),
                'quincenal' => $periodoInicio->copy()->addWeeks(2)->subDay(),
                'mensual' => $periodoInicio->copy()->addMonth()->subDay(),
                default => $periodoInicio->copy()->addWeek()->subDay()
            };

            $periodo = PeriodoCuotaSocio::create([
                'cuota_socio_id' => $cuota->id,
                'socio_id' => $socio->id,
                'periodo_inicio' => $periodoInicio,
                'periodo_fin' => $fechaFin,
                'monto_esperado' => $cuota->monto_cuota,
                'estado' => 'pendiente',
                'fecha_vencimiento' => $fechaFin,
                'notificado_vencimiento' => false
            ]);

            $periodos[] = $periodo;
        }

        return $periodos;
    }

    /**
     * Marca períodos como vencidos si ya pasó su fecha_vencimiento
     * Este método debe ejecutarse diariamente con un cron job
     *
     * @return int Cantidad de períodos marcados como vencidos
     */
    public function marcarPeriodosVencidos()
    {
        $cantidadActualizada = PeriodoCuotaSocio::where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', Carbon::now()->startOfDay())
            ->update(['estado' => 'vencido']);

        return $cantidadActualizada;
    }

    /**
     * Obtiene períodos próximos a vencer (5 días) que aún no han sido notificados
     *
     * @param int $dias Días antes del vencimiento (por defecto 5)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerPeriodosProximosAVencerSinNotificar($dias = 5)
    {
        $hoy = Carbon::now()->startOfDay();
        $fechaLimite = $hoy->copy()->addDays($dias);

        return PeriodoCuotaSocio::where('estado', 'pendiente')
            ->where('notificado_vencimiento', false)
            ->where('fecha_vencimiento', '>=', $hoy)
            ->where('fecha_vencimiento', '<=', $fechaLimite)
            ->with(['socio', 'cuota'])
            ->get();
    }

    /**
     * Marca un período como notificado
     *
     * @param int $periodoId
     * @return bool
     */
    public function marcarComoNotificado($periodoId)
    {
        $periodo = PeriodoCuotaSocio::find($periodoId);

        if ($periodo) {
            $periodo->update(['notificado_vencimiento' => true]);
            return true;
        }

        return false;
    }

    /**
     * Vincula un pago a un período y marca como "en revisión"
     *
     * @param int $periodoId
     * @param int $pagoId
     * @return PeriodoCuotaSocio|null
     */
    public function vincularPagoAPeriodo($periodoId, $pagoId)
    {
        $periodo = PeriodoCuotaSocio::find($periodoId);

        if ($periodo && $periodo->estado === 'pendiente') {
            $periodo->update([
                'pago_id' => $pagoId,
                'estado' => 'en_revision'
            ]);
            return $periodo;
        }

        return null;
    }

    /**
     * Marca un período como pagado (cuando admin aprueba el pago)
     *
     * @param int $periodoId
     * @return PeriodoCuotaSocio|null
     */
    public function marcarComoPagado($periodoId)
    {
        $periodo = PeriodoCuotaSocio::find($periodoId);

        if ($periodo && $periodo->estado === 'en_revision') {
            $periodo->update(['estado' => 'pagado']);
            return $periodo;
        }

        return null;
    }

    /**
     * Marca un período como pendiente nuevamente (cuando admin rechaza el pago)
     *
     * @param int $periodoId
     * @return PeriodoCuotaSocio|null
     */
    public function marcarComoPendiente($periodoId)
    {
        $periodo = PeriodoCuotaSocio::find($periodoId);

        if ($periodo && $periodo->estado === 'en_revision') {
            $periodo->update([
                'estado' => 'pendiente',
                'pago_id' => null
            ]);
            return $periodo;
        }

        return null;
    }

    /**
     * Obtiene el período actual de un socio
     * Si no hay período activo hoy, retorna el próximo período pendiente
     *
     * @param int $socioId
     * @return PeriodoCuotaSocio|null
     */
    public function obtenerPeriodoActualDeSocio($socioId)
    {
        $hoy = Carbon::now();

        // Primero buscar el período activo (hoy está entre periodo_inicio y periodo_fin)
        $periodoActual = PeriodoCuotaSocio::where('socio_id', $socioId)
            ->where('periodo_inicio', '<=', $hoy)
            ->where('periodo_fin', '>=', $hoy)
            ->with(['cuota', 'pago'])
            ->first();

        // Si encontró un período activo, agregar información calculada
        if ($periodoActual) {
            if ($periodoActual->fecha_vencimiento) {
                $fechaVencimiento = Carbon::parse($periodoActual->fecha_vencimiento);
                $diasDiferencia = $hoy->diffInDays($fechaVencimiento, false);
                $periodoActual->dias_para_vencer = $diasDiferencia >= 0 ? (int)$diasDiferencia : null;
                $periodoActual->esta_vencido = $hoy->isAfter($fechaVencimiento);
            }
            return $periodoActual;
        }

        // Si no hay período activo, buscar el próximo período pendiente o vencido
        $siguientePeriodo = PeriodoCuotaSocio::where('socio_id', $socioId)
            ->whereIn('estado', ['pendiente', 'vencido'])
            ->orderBy('periodo_inicio', 'asc')
            ->with(['cuota', 'pago'])
            ->first();

        // Agregar información calculada al siguiente período
        if ($siguientePeriodo && $siguientePeriodo->fecha_vencimiento) {
            $fechaVencimiento = Carbon::parse($siguientePeriodo->fecha_vencimiento);
            $diasDiferencia = $hoy->diffInDays($fechaVencimiento, false);
            $siguientePeriodo->dias_para_vencer = $diasDiferencia >= 0 ? (int)$diasDiferencia : null;
            $siguientePeriodo->esta_vencido = $hoy->isAfter($fechaVencimiento);
        }

        return $siguientePeriodo;
    }

    /**
     * Obtiene todos los períodos de un socio con información adicional calculada
     *
     * @param int $socioId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerPeriodosDeSocio($socioId)
    {
        $hoy = Carbon::now();

        $query = PeriodoCuotaSocio::where('socio_id', $socioId)
            ->with(['cuota', 'pago']);

        // Para cuotas tipo porcentaje: solo mostrar períodos que ya iniciaron o tienen actividad
        // Para monto fijo: mostrar todos
        $primerPeriodo = PeriodoCuotaSocio::where('socio_id', $socioId)->first();
        if ($primerPeriodo && $primerPeriodo->cuota && $primerPeriodo->cuota->tipo_cuota === 'porcentaje') {
            $query->where(function ($q) use ($hoy) {
                $q->where('periodo_inicio', '<=', $hoy)
                  ->orWhere('estado', '!=', 'pendiente')
                  ->orWhere('total_ventas', '>', 0)
                  ->orWhere('monto_esperado', '>', 0);
            });
        }

        $periodos = $query->orderBy('periodo_inicio', 'asc')->get();

        // Agregar información calculada a cada período
        $numeroPeriodo = 1;

        $periodos->transform(function ($periodo) use ($hoy, &$numeroPeriodo) {
            // Agregar número de período secuencial
            $periodo->numero_periodo = $numeroPeriodo++;

            // Calcular días para vencer
            if ($periodo->fecha_vencimiento) {
                $fechaVencimiento = Carbon::parse($periodo->fecha_vencimiento);
                $diasDiferencia = $hoy->diffInDays($fechaVencimiento, false);

                // Si es negativo, ya venció
                $periodo->dias_para_vencer = $diasDiferencia >= 0 ? (int)$diasDiferencia : null;
                $periodo->esta_vencido = $hoy->isAfter($fechaVencimiento);
            } else {
                $periodo->dias_para_vencer = null;
                $periodo->esta_vencido = false;
            }

            return $periodo;
        });

        return $periodos;
    }

    /**
     * Verifica si un socio puede acceder al sistema
     *
     * @param int $socioId
     * @return array ['puede_acceder' => bool, 'motivo' => string|null, 'dias_vencimiento' => int|null]
     */
    public function verificarAccesoSocio($socioId)
    {
        $socio = BusinessRegistration::find($socioId);

        // Si no tiene cuota asignada, puede acceder (no bloqueamos si no tiene cuota)
        if (!$socio || !$socio->tieneCuotaAsignada()) {
            return [
                'puede_acceder' => true,
                'can_access' => true,
                'motivo' => 'sin_cuota_asignada',
                'mensaje' => 'No tienes una cuota asignada.',
                'message' => 'No tienes una cuota asignada.',
                'dias_vencimiento' => null
            ];
        }

        // Si tiene períodos vencidos, no puede acceder
        if ($socio->tienePeriodosVencidos()) {
            $periodosVencidos = PeriodoCuotaSocio::where('socio_id', $socio->id)
                ->where('estado', 'vencido')
                ->orderBy('fecha_vencimiento', 'asc')
                ->get();

            $diasVencimiento = 0;
            if ($periodosVencidos->count() > 0) {
                $primerVencido = $periodosVencidos->first();
                $diasVencimiento = (int) abs(Carbon::now()->startOfDay()->diffInDays(
                    Carbon::parse($primerVencido->fecha_vencimiento)->startOfDay(),
                    false
                ));
            }

            return [
                'puede_acceder' => false,
                'can_access' => false,
                'motivo' => 'cuota_vencida',
                'mensaje' => 'Tienes pagos vencidos. Por favor, regulariza tu situación.',
                'message' => 'Tienes pagos vencidos. Por favor, regulariza tu situación.',
                'dias_vencimiento' => $diasVencimiento
            ];
        }

        // Verificar si hay períodos próximos a vencer (5 días)
        $periodosProximos = $socio->periodosProximosAVencer();

        if ($periodosProximos->count() > 0) {
            $primerPeriodo = $periodosProximos->first();
            $diasParaVencer = (int) Carbon::now()->startOfDay()->diffInDays(
                Carbon::parse($primerPeriodo->fecha_vencimiento)->startOfDay(),
                false
            );

            return [
                'puede_acceder' => true,
                'can_access' => true,
                'motivo' => 'proximo_vencimiento',
                'mensaje' => "Tu cuota vence en {$diasParaVencer} días. Recuerda realizar tu pago.",
                'message' => "Tu cuota vence en {$diasParaVencer} días. Recuerda realizar tu pago.",
                'dias_vencimiento' => $diasParaVencer,
                'periodo' => $primerPeriodo
            ];
        }

        // Todo está bien
        return [
            'puede_acceder' => true,
            'can_access' => true,
            'motivo' => null,
            'mensaje' => null,
            'message' => null,
            'dias_vencimiento' => null
        ];
    }

    /**
     * Regenera períodos cuando se cambia la cuota de un socio
     *
     * @param int $socioId
     * @param int $nuevaCuotaId
     * @return array
     */
    public function cambiarCuotaDeSocio($socioId, $nuevaCuotaId)
    {
        return $this->generarPeriodosParaSocio($socioId, $nuevaCuotaId, 12);
    }
}
