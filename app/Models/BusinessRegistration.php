<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRegistration extends Model
{
    protected $fillable = [
        'name',
        'lastName',
        'businessType',
        'phone',
        'email',
        'verification_code',
        'email_verified_at',
        'estado'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'integer'
    ];

    public function negocio()
    {
        return $this->hasOne(Negocio::class);
    }

    public function establecimiento()
    {
        return $this->hasOne(Establecimiento::class);
    }

    public function datosClaveNegocio()
    {
        return $this->hasOne(DatosClaveNegocio::class);
    }

    public function datosBancarios()
    {
        return $this->hasOne(DatosBancarios::class);
    }

    public function revisarDatos()
    {
        return $this->hasOne(RevisarDatos::class);
    }
}