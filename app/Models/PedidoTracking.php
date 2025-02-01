<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoTracking extends Model
{
    use HasFactory;
    protected $table = 'pedido_trackings';
    protected $fillable = ['pedido_id', 'estado'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
