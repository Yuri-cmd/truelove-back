<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatosClaveNegocio extends Model
{
    protected $table = 'datos_clave_negocio';
    
    protected $fillable = [
        'ruc',
        'razon_social',
        'business_registration_id'
    ];

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    public function revisarDatos()
    {
        return $this->hasOne(RevisarDatos::class);
    }

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}