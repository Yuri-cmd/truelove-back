<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';

    protected $fillable = [
        'titulo',
        'subtitulo',
        'color_fondo',
        'texto_boton',
        'url_boton',
        'estado',
        'url_imagen'
    ];
}
