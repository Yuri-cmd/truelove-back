<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';
    protected $fillable = ['titulo', 'subtitulo', 'imagen', 'estado'];
}
