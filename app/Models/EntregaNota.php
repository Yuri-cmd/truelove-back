<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EntregaNota extends Model
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA = 'aprobada';
    public const RECHAZADA = 'rechazada';

    protected $table = 'entrega_notas';

    protected $fillable = [
        'motorizado_id', 'pedido_id', 'cliente_id',
        'latitud', 'longitud', 'direccion', 'id_direccion', 'direccion_version', 'nota', 'foto_path',
        'estado', 'revisada_por', 'revisada_at', 'motivo_rechazo',
    ];

    protected $casts = [
        'revisada_at' => 'datetime',
    ];

    protected $appends = ['foto_url'];

    public function motorizado()
    {
        return $this->belongsTo(RepartoRegistro::class, 'motorizado_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto_path
            ? url(Storage::disk('custom_public')->url($this->foto_path))
            : null;
    }

    /** Notas que ven los repartidores: todas menos las rechazadas por el admin. */
    public function scopeVisiblesParaRepartidores($query)
    {
        return $query->where('estado', '!=', self::RECHAZADA);
    }
}
