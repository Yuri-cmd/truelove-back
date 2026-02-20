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
        'tipo_cuota', // 'monto_fijo', 'porcentaje'
        'monto_cuota',
        'porcentaje_comision', // Porcentaje para tipo 'porcentaje'
        'minimo_pedidos', // Mínimo de pedidos para cobrar
        'exonerar_si_menos_pedidos', // Si TRUE, exonera si no alcanza el mínimo
        'monto_minimo', // Monto mínimo para tipo mixto
        'monto_uso_app', // Monto por uso de app cuando no alcanza mínimo
        'monto_maximo', // Tope máximo de comisión por período
        'numero_cuenta',
        'tipo_cuenta',
        'banco',
        'metodos_pago_disponibles',
        'estado',
        'descripcion',
        'dia_pago', // Día del mes para realizar el pago (1-31)
        'dia_pago_nota' // Notas explicativas sobre el día de pago
    ];

    protected $casts = [
        'monto_cuota' => 'decimal:2',
        'porcentaje_comision' => 'decimal:2',
        'minimo_pedidos' => 'integer',
        'exonerar_si_menos_pedidos' => 'boolean',
        'monto_minimo' => 'decimal:2',
        'monto_uso_app' => 'decimal:2',
        'monto_maximo' => 'decimal:2',
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

    /**
     * Obtiene el día de pago, usando un fallback si no está definido
     */
    public function getDiaPagoAttribute($value)
    {
        return $value ?? 1; // Fallback al día 1 si no está definido
    }

    /**
     * Calcula los días restantes hasta el próximo día de pago
     */
    public function diasHastaPago()
    {
        if (!$this->dia_pago) {
            return null;
        }

        $hoy = Carbon::now();
        $diaPago = $this->dia_pago;
        
        // Obtener el próximo día de pago
        $proximoPago = Carbon::create($hoy->year, $hoy->month, min($diaPago, $hoy->daysInMonth));
        
        // Si ya pasó este mes, ir al siguiente
        if ($proximoPago->lt($hoy)) {
            $proximoPago = $proximoPago->addMonth();
            // Ajustar para meses con menos días
            $proximoPago->day = min($diaPago, $proximoPago->daysInMonth);
        }
        
        return $hoy->diffInDays($proximoPago, false);
    }

    /**
     * Obtiene la próxima fecha de pago
     */
    public function getProximaFechaPago()
    {
        if (!$this->dia_pago) {
            return null;
        }

        $hoy = Carbon::now();
        $diaPago = $this->dia_pago;
        
        // Obtener el próximo día de pago
        $proximoPago = Carbon::create($hoy->year, $hoy->month, min($diaPago, $hoy->daysInMonth));
        
        // Si ya pasó este mes, ir al siguiente
        if ($proximoPago->lt($hoy)) {
            $proximoPago = $proximoPago->addMonth();
            // Ajustar para meses con menos días
            $proximoPago->day = min($diaPago, $proximoPago->daysInMonth);
        }
        
        return $proximoPago;
    }

    /**
     * Verifica si necesita mostrar nota explicativa para días > 28
     */
    public function necesitaNotaExplicativa()
    {
        return $this->dia_pago && $this->dia_pago > 28;
    }

    /**
     * Obtiene la nota explicativa automática para días > 28
     */
    public function getNotaExplicativaAutomatica()
    {
        if (!$this->necesitaNotaExplicativa()) {
            return null;
        }

        return "En meses con menos de {$this->dia_pago} días, el pago se realizará el último día del mes.";
    }
}