<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaRango extends Model
{
    use HasFactory;

    protected $table = 'tarifa_rangos';

    protected $fillable = [
        'kilometros_tarifa_id',
        'distancia_desde',
        'distancia_hasta',
        'precio_diurno',
        'precio_nocturno',
        'orden'
    ];

    protected $casts = [
        'distancia_desde' => 'decimal:2',
        'distancia_hasta' => 'decimal:2',
        'precio_diurno' => 'decimal:2',
        'precio_nocturno' => 'decimal:2',
        'orden' => 'integer'
    ];

    /**
     * Relación con kilometros_tarifa
     */
    public function kilometrosTarifa()
    {
        return $this->belongsTo(KilometrosTarifa::class, 'kilometros_tarifa_id');
    }

    /**
     * Accessor para mostrar el rango formateado
     */
    public function getRangoFormateadoAttribute()
    {
        $desde = number_format($this->distancia_desde, 2);
        $hasta = $this->distancia_hasta ? number_format($this->distancia_hasta, 2) : '∞';
        return "{$desde} - {$hasta} km";
    }

    /**
     * Verificar si una distancia cae en este rango
     * 
     * @param float $distanciaKm
     * @return bool
     */
    public function contieneDistancia($distanciaKm)
    {
        $entraDesde = $distanciaKm >= $this->distancia_desde;
        $entraHasta = is_null($this->distancia_hasta) || $distanciaKm <= $this->distancia_hasta;
        
        return $entraDesde && $entraHasta;
    }
}
