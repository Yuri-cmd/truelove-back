<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioNegocio extends Model
{
    use HasFactory;

    protected $table = 'horarios_negocio';

    protected $fillable = [
        'perfil_negocio_id',
        'nombre',
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'sabado',
        'domingo',
        'hora_apertura',
        'hora_cierre',
        'activo',
    ];

    protected $casts = [
        'lunes' => 'boolean',
        'martes' => 'boolean',
        'miercoles' => 'boolean',
        'jueves' => 'boolean',
        'viernes' => 'boolean',
        'sabado' => 'boolean',
        'domingo' => 'boolean',
        'activo' => 'boolean',
    ];

    public function perfilNegocio()
    {
        return $this->belongsTo(PerfilNegocio::class);
    }
}