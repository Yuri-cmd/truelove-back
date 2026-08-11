<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';
    protected $fillable = [
        'business_registration_id',
        'titulo', 'subtitulo', 'imagen', 'estado',
        'tipo_destino', 'pantalla', 'destino_id',
    ];

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}
