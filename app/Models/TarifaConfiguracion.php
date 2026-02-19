<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaConfiguracion extends Model
{
    use HasFactory;

    protected $table = 'tarifa_configuraciones';

    protected $fillable = [
        'nombre',
        'descripcion',
        'hora_inicio_nocturno',
        'hora_fin_nocturno',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'hora_inicio_nocturno' => 'datetime:H:i',
        'hora_fin_nocturno' => 'datetime:H:i'
    ];

    /**
     * Relación con rangos
     */
    public function rangos()
    {
        return $this->hasMany(TarifaRango::class, 'configuracion_id')->orderBy('orden');
    }

    /**
     * Scope para obtener solo configuraciones activas
     */
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Método estático para obtener la configuración activa
     */
    public static function getConfiguracionActiva()
    {
        return static::with('rangos')->where('activo', true)->first();
    }

    /**
     * Método para activar esta configuración y desactivar las demás
     */
    public function activar()
    {
        // Desactivar todas las demás configuraciones
        static::where('id', '!=', $this->id)->update(['activo' => false]);

        // Activar esta configuración
        $this->update(['activo' => true]);

        return $this;
    }

    /**
     * Verificar si una hora está en el rango nocturno
     * 
     * @param string $hora Hora en formato H:i o H:i:s
     * @return bool
     */
    public function esHorarioNocturno($hora = null)
    {
        if (!$hora) {
            $hora = \Carbon\Carbon::now('America/Lima')->format('H:i:s');
        }

        // Convertir strings a objetos Carbon para comparación
        $horaActual = \Carbon\Carbon::createFromFormat('H:i:s', $hora);
        $horaInicio = \Carbon\Carbon::createFromFormat('H:i:s', $this->hora_inicio_nocturno);
        $horaFin = \Carbon\Carbon::createFromFormat('H:i:s', $this->hora_fin_nocturno);

        // Si el rango cruza la medianoche (ej: 23:00 - 05:00)
        if ($horaFin->lt($horaInicio)) {
            return $horaActual->gte($horaInicio) || $horaActual->lte($horaFin);
        }

        // Rango normal (ej: 19:00 - 23:59)
        return $horaActual->gte($horaInicio) && $horaActual->lte($horaFin);
    }

    /**
     * Calcular precio para una distancia dada
     * 
     * @param float $distanciaKm
     * @param string|null $hora
     * @return float
     */
    public function calcularPrecio($distanciaKm, $hora = null)
    {
        // Buscar el rango apropiado
        $rango = $this->rangos()
            ->where('distancia_desde', '<=', $distanciaKm)
            ->where(function($query) use ($distanciaKm) {
                $query->where('distancia_hasta', '>=', $distanciaKm)
                      ->orWhereNull('distancia_hasta');
            })
            ->orderBy('orden')
            ->first();

        if (!$rango) {
            return 5.00; // Precio por defecto si no hay rango
        }

        $esNocturno = $this->esHorarioNocturno($hora);
        return $esNocturno ? $rango->precio_nocturno : $rango->precio_diurno;
    }
}
