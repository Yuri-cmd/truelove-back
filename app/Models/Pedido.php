<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;
    protected $table = 'pedidos';
    protected $fillable = ['id_local', 'id_cliente', 'id_motorizado', 'latitud', 'longitud', 'tiempo', 'nota'];

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class);
    }

    public function trackings()
    {
        return $this->hasMany(PedidoTracking::class);
    }
}
