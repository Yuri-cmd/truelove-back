<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntregaCalendario extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'hora',
        'reparto_registro_id',
        'estado'
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function repartoRegistro()
    {
        return $this->belongsTo(RepartoRegistro::class);
    }
}