<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CuotaSocio extends Model
{
    use HasFactory;

    protected $table = 'cuotas_socios';

    protected $fillable = [
        'periodicidad', // 'diario', 'semanal', 'quincenal', 'mensual'
        'monto_cuota',
        'numero_cuenta',
        'tipo_cuenta',
        'banco',
        'metodos_pago_disponibles',
        'estado',
        'descripcion'
    ];

    protected $casts = [
        'monto_cuota' => 'decimal:2',
        'metodos_pago_disponibles' => 'array' // Convierte JSON a array automáticamente
    ];

    // Relación con pagos
    public function pagos()
    {
        return $this->hasMany(PagoCuotaSocio::class, 'cuota_socio_id');
    }

    // Scopes
    public function scopeActiva($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeInactiva($query)
    {
        return $query->where('estado', 'inactivo');
    }

    // Scope removido: vigente() ya no aplica porque las fechas están en los períodos
    // La vigencia se determina cuando se asigna la cuota al socio
}