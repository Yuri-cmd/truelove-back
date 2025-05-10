<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DescuentoCliente extends Model
{
    use HasFactory;
    
    protected $table = 'descuentos_clientes';
    protected $fillable = [
        'id_cliente',
        'tipo_descuento', // 'porcentaje', 'monto_fijo', 'delivery_gratis'
        'valor',          // Valor del descuento (porcentaje o monto fijo)
        'codigo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'cantidad_usos',
        'usos_disponibles',
        'descripcion'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'estado' => 'boolean',
        'valor' => 'decimal:2'
    ];

    // Relación con el cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
    
    // Verificar si el descuento está vigente
    public function estaVigente()
    {
        $hoy = now()->startOfDay();
        
        // Verificar fecha de inicio y fin
        $fechaValida = $hoy->gte($this->fecha_inicio) && 
                      ($this->fecha_fin === null || $hoy->lte($this->fecha_fin));
        
        // Verificar usos disponibles
        $usosValidos = $this->usos_disponibles === null || 
                       $this->cantidad_usos < $this->usos_disponibles;
        
        return $this->estado && $fechaValida && $usosValidos;
    }
    
    // Aplicar el descuento a un monto
    public function aplicarA($monto, $costoDelivery = 0)
    {
        if (!$this->estaVigente()) {
            return ['monto' => $monto, 'delivery' => $costoDelivery, 'descuento' => 0];
        }
        
        $descuento = 0;
        $nuevoMonto = $monto;
        $nuevoDelivery = $costoDelivery;
        
        switch ($this->tipo_descuento) {
            case 'porcentaje':
                $descuento = $monto * ($this->valor / 100);
                $nuevoMonto = $monto - $descuento;
                break;
                
            case 'monto_fijo':
                $descuento = min($monto, $this->valor);
                $nuevoMonto = $monto - $descuento;
                break;
                
            case 'delivery_gratis':
                $descuento = $costoDelivery;
                $nuevoDelivery = 0;
                break;
        }
        
        return [
            'monto' => $nuevoMonto,
            'delivery' => $nuevoDelivery,
            'descuento' => $descuento
        ];
    }
}