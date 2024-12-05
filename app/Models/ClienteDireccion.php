<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ClienteDireccion extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_cliente',
        'direccion',
        'departamento',
        'referencia',
        'alias',
        'coordenadas',
    ];
}
