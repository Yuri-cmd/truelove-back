<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagoCuotaSocio extends Model
{
    use HasFactory;

    protected $table = 'pagos_cuotas_socios';

    protected $fillable = [
        'cuota_socio_id',
        'socio_id',
        'comprobante_pago', //ruta de la captura del comprobante
        'estado_pago', // 'pendiente', 'aprobado', 'rechazado'
        'fecha_pago',
        'monto_pagado',
        'metodo_pago',
        'numero_operacion',
        'observaciones',
        'fecha_aprobacion',
        'aprobado_por',
        'motivo_rechazo'
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'fecha_aprobacion' => 'datetime',
        'monto_pagado' => 'decimal:2'
    ];

    // Relación con cuota
    public function cuota()
    {
        return $this->belongsTo(CuotaSocio::class, 'cuota_socio_id');
    }

    // Relación con socio (business_registration)
    public function socio()
    {
        return $this->belongsTo(BusinessRegistration::class, 'socio_id')
                    ->select('id', 'name', 'lastName', 'email', 'phone');
    }

    // Relación con admin que aprobó
    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado_pago', 'pendiente');
    }

    public function scopeAprobados($query)
    {
        return $query->where('estado_pago', 'aprobado');
    }

    public function scopeRechazados($query)
    {
        return $query->where('estado_pago', 'rechazado');
    }
}