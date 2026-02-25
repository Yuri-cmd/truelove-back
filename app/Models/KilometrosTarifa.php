<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KilometrosTarifa extends Model
{
    use HasFactory;

    protected $table = 'kilometros_tarifa';

    protected $fillable = [
        'precio_base_diurno',
        'precio_base_nocturno',
        'precio_por_km_diurno',
        'precio_por_km_nocturno',
        'precio_maximo',
        'distancia_maxima',
        'distancia_minima',
        'activo',
        'nombre',
        'descripcion',
        'hora_inicio_nocturno',
        'hora_fin_nocturno',
        'business_registration_id',
        'modo_tarifa',
    ];

    protected $casts = [
        'precio_base_diurno' => 'decimal:2',
        'precio_base_nocturno' => 'decimal:2',
        'precio_por_km_diurno' => 'decimal:2',
        'precio_por_km_nocturno' => 'decimal:2',
        'precio_maximo' => 'decimal:2',
        'distancia_maxima' => 'decimal:2',
        'distancia_minima' => 'decimal:2',
        'activo' => 'boolean',
        'business_registration_id' => 'integer',
    ];

    // Scope para obtener solo configuraciones activas
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    // Método estático para obtener la configuración activa
    // Si se pasa $idLocal, busca primero config propia del local; si no, usa la global
    public static function getConfiguracionActiva($idLocal = null)
    {
        if ($idLocal) {
            $config = static::where('business_registration_id', $idLocal)
                ->where('activo', true)
                ->first();
            if ($config) return $config;
        }
        // Fallback: config global (sin local asignado)
        return static::whereNull('business_registration_id')
            ->where('activo', true)
            ->first();
    }

    // Método para activar esta configuración y desactivar las demás del mismo ámbito
    public function activar()
    {
        if ($this->business_registration_id) {
            // Es config de un local: solo desactivar otras configs del MISMO local
            static::where('id', '!=', $this->id)
                ->where('business_registration_id', $this->business_registration_id)
                ->update(['activo' => false]);
        } else {
            // Es config global: solo desactivar otras configs globales (sin local)
            static::where('id', '!=', $this->id)
                ->whereNull('business_registration_id')
                ->update(['activo' => false]);
        }

        // Activar esta configuración
        $this->update(['activo' => true]);

        return $this;
    }

    // Relación con rangos de tarifa
    public function rangos()
    {
        return $this->hasMany(TarifaRango::class, 'kilometros_tarifa_id', 'id')->orderBy('orden');
    }
}