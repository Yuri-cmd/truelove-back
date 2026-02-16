<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PeriodoCuotaSocio extends Model
{
    use HasFactory;

    protected $table = 'periodos_cuotas_socios';

    protected $fillable = [
        'cuota_socio_id',
        'socio_id',
        'periodo_inicio',
        'periodo_fin',
        'monto_esperado',
        'total_ventas',
        'cantidad_pedidos',
        'monto_calculado',
        'fecha_calculo',
        'estado',
        'pago_id',
        'fecha_vencimiento',
        'notificado_vencimiento'
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'fecha_vencimiento' => 'date',
        'monto_esperado' => 'decimal:2',
        'total_ventas' => 'decimal:2',
        'cantidad_pedidos' => 'integer',
        'monto_calculado' => 'decimal:2',
        'fecha_calculo' => 'datetime',
        'notificado_vencimiento' => 'boolean'
    ];

    protected $appends = ['numero_periodo', 'dias_para_vencer', 'esta_vencido'];

    // Relación con cuota
    public function cuota()
    {
        return $this->belongsTo(CuotaSocio::class, 'cuota_socio_id');
    }

    // Relación con socio
    public function socio()
    {
        return $this->belongsTo(BusinessRegistration::class, 'socio_id')
                    ->select('id', 'name', 'lastName', 'email', 'phone');
    }

    // Relación con pago
    public function pago()
    {
        return $this->belongsTo(PagoCuotaSocio::class, 'pago_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnRevision($query)
    {
        return $query->where('estado', 'en_revision');
    }

    public function scopePagados($query)
    {
        return $query->where('estado', 'pagado');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado', 'vencido');
    }

    public function scopeActual($query)
    {
        $hoy = Carbon::now();
        return $query->where('periodo_inicio', '<=', $hoy)
                    ->where('periodo_fin', '>=', $hoy);
    }

    public function scopeProximosAVencer($query, $dias = 5)
    {
        $hoy = Carbon::now();
        $fechaLimite = $hoy->copy()->addDays($dias);

        return $query->where('estado', 'pendiente')
                    ->where('fecha_vencimiento', '>=', $hoy)
                    ->where('fecha_vencimiento', '<=', $fechaLimite);
    }

    public function scopePorSocio($query, $socioId)
    {
        return $query->where('socio_id', $socioId);
    }

    // Atributos computados
    public function getNumeroPeriodoAttribute()
    {
        if (!$this->socio_id) {
            return 1;
        }

        // Calcular el número de período basado en la posición cronológica
        return PeriodoCuotaSocio::where('socio_id', $this->socio_id)
            ->where('periodo_inicio', '<', $this->periodo_inicio)
            ->count() + 1;
    }

    public function getEstaVencidoAttribute()
    {
        return $this->estado === 'vencido' ||
               ($this->estado === 'pendiente' && Carbon::now()->gt($this->fecha_vencimiento));
    }

    public function getDiasParaVencerAttribute()
    {
        if ($this->estado !== 'pendiente') {
            return null;
        }

        // Calcular diferencia en días (entero)
        $dias = Carbon::now()->startOfDay()->diffInDays(
            Carbon::parse($this->fecha_vencimiento)->startOfDay(),
            false
        );

        return (int) $dias;
    }

    public function getProximoAVencerAttribute()
    {
        $dias = $this->dias_para_vencer;
        return $dias !== null && $dias >= 0 && $dias <= 5;
    }
}
