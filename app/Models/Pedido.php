<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;
    protected $table = 'pedidos';
    protected $fillable = ['id_local', 'id_cliente', 'id_motorizado', 'latitud', 'longitud', 'tiempo', 'nota', 'id_tipo_pago', 'tipo_comprobante', 'documento', 'precio_delivery', 'requiere_confirmacion_local', 'subtotal', 'descuento', 'codigo', 'foto_pago'];

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function trackings()
    {
        return $this->hasMany(PedidoTracking::class);
    }
}
