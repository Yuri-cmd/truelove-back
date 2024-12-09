<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatosPersonalesReparto extends Model
{
    use HasFactory;

    protected $table = 'datos_personales';

    protected $fillable = [
        'fecha_nacimiento',
        'genero',
        'url_selfie',
        'ciudad_id',
        'distrito_id'
    ];

    public function ciudad()
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}

