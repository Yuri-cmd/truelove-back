<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Pedido extends Model
{
    use HasFactory;
    protected $table = 'pedidos';
    protected $fillable = ['clave_idempotencia', 'id_local', 'id_cliente', 'id_motorizado', 'latitud', 'longitud', 'id_direccion', 'direccion_version', 'cliente_latitud', 'cliente_longitud', 'direccion', 'referencia', 'tiempo', 'nota', 'id_tipo_pago', 'tipo_comprobante', 'documento', 'precio_delivery', 'requiere_confirmacion_local', 'subtotal', 'descuento', 'codigo', 'foto_pago', 'tipo_pedido', 'fecha_hora_inicio', 'paga_con'];
    protected $casts = [
        'fecha_hora_inicio' => 'datetime',
    ];

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function trackings()
    {
        return $this->hasMany(PedidoTracking::class);
    }

    /**
     * Pedidos completados (8 = entregado, 9 = recojo en local) cuya fecha y hora de
     * completado cae en el rango. Se usa para asignar ventas a períodos de cuota:
     * un pedido cuenta en el período en que se completó, no en el que se creó.
     */
    public function scopeCompletadosEntre($query, $desde, $hasta)
    {
        return $query->whereRaw(
            '(select min(pt.created_at) from pedido_trackings pt where pt.pedido_id = pedidos.id and pt.estado in (8, 9)) between ? and ?',
            [Carbon::parse($desde), Carbon::parse($hasta)]
        );
    }
}
