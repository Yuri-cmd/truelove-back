<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatosClaveNegocio extends Model
{
    protected $table = 'datos_clave_negocio';
    
    protected $fillable = [
        'ruc',
        'razon_social'
     
    ];

    // Relación con el negocio
    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    // Relación con RevisarDatos
    public function revisarDatos()
    {
        return $this->hasOne(RevisarDatos::class);
    }
}