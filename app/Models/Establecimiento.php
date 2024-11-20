<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';
    
    protected $fillable = [
        'nombre_establecimiento',
        'calle',
        'numero',
        'codigo_postal',
        'provincia',
        'ciudad',
        'referencia',
        'latitud',
        'longitud',
        'direccion_completa'
    ];
}