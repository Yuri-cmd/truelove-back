<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CuotaMotorizado extends Model
{
    use HasFactory;

    protected $table = 'cuotas_motorizados';

    protected $fillable = [
        'motorizado_id',
        'semana_inicio',
        'semana_fin',
        'monto_cuota',
        'estado_pago',
        'fecha_pago',
        'fecha_vencimiento',
        'observaciones',
        'metodo_pago',
        'comprobante_pago'
    ];

    protected $casts = [
        'semana_inicio' => 'date',
        'semana_fin' => 'date',
        'fecha_pago' => 'datetime',
        'fecha_vencimiento' => 'date',
        'monto_cuota' => 'decimal:2'
    ];

    // Relación con motorizado
    public function motorizado()
    {
        return $this->belongsTo(RepartoRegistro::class, 'motorizado_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado_pago', 'pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('estado_pago', 'pagado');
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado_pago', 'vencido')
                    ->orWhere(function($q) {
                        $q->where('estado_pago', 'pendiente')
                          ->where('fecha_vencimiento', '<', Carbon::now());
                    });
    }

    public function scopeSemanaActual($query)
    {
        $inicioSemana = Carbon::now()->startOfWeek();
        $finSemana = Carbon::now()->endOfWeek();
        
        return $query->where('semana_inicio', '>=', $inicioSemana)
                    ->where('semana_fin', '<=', $finSemana);
    }

    // Mutators y Accessors
    public function getEstadoPagoColorAttribute()
    {
        return match($this->estado_pago) {
            'pagado' => 'green',
            'pendiente' => 'yellow',
            'vencido' => 'red',
            default => 'gray'
        };
    }

    public function getDiasVencimientoAttribute()
    {
        if ($this->estado_pago === 'pagado') {
            return null;
        }
        
        return Carbon::now()->diffInDays($this->fecha_vencimiento, false);
    }
}