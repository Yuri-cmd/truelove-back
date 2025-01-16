<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorias extends Model
{
    protected $table = 'categoria';
    protected $fillable = [
        'empresa_id',
        'nombre',
    ];
}
