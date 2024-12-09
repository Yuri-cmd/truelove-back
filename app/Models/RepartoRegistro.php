<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepartoRegistro extends Model
{
    use HasFactory;

    protected $fillable = [
        'departamento',
        'vehiculo',
        'tipo_documento',
        'nro_documento',
        'nombres',
        'apellidos',
        'celular',
        'email',
        'mayor_edad',
        'acepta_politica',
        'documento_imagen',
    ];

    protected $casts = [
        'mayor_edad' => 'boolean',
        'acepta_politica' => 'boolean',
    ];
}

