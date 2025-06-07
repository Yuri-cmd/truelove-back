<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionCuota extends Model
{
    use HasFactory;

    protected $table = 'configuracion_cuotas';

    protected $fillable = [
        'nombre',
        'monto_semanal',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'monto_semanal' => 'decimal:2',
        'activo' => 'boolean'
    ];

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}