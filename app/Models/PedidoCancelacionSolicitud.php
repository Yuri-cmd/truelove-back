<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PedidoCancelacionSolicitud extends Model
{
    use HasFactory;

    protected $table = 'pedido_cancelacion_solicitudes';

    protected $fillable = [
        'pedido_id',
        'estado_pedido_al_solicitar',
        'motivo',
        'status',
        'solicitado_por_socio_id',
        'revisado_por_admin_id',
        'revisado_at',
    ];

    protected $casts = [
        'revisado_at' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por_admin_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
