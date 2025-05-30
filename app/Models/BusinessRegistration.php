<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRegistration extends Model
{
    protected $fillable = [
        'documentType',
        'documentNumber',
        'name',
        'lastName',
        'businessType',
        'phone',
        'email',
        'verification_code',
        'email_verified_at',
        'estado',
        'aprobado',
        'posToDriver',
        'activo',
        'token_fmc'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'integer',
        'aprobado' => 'boolean',
        'posToDriver' =>'boolean'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

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
    public function cuentaBancaria()
    {
        return $this->hasOne(SociosCuentaBancaria::class);
    }

    public function revisarDatos()
    {
        return $this->hasOne(RevisarDatos::class);
    }

    public function documentosPdfExtranjero()
    {
        return $this->hasOne(DocPdfExtranjero::class);
    }

    public function perfil()
    {
        return $this->hasOne(PerfilNegocio::class);

    }
}