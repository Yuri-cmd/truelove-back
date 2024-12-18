<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatosBancarios extends Model
{
    protected $fillable = [
        'titular_cuenta',
        'numero_cuenta',
        'nombre_banco',
        'tipo_cuenta',
        'documento_titular',
        'codigo_cci',
        'usar_direccion_negocio',
        'establecimiento_id',
        'business_registration_id'
    ];

    protected $casts = [
        'usar_direccion_negocio' => 'boolean',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}