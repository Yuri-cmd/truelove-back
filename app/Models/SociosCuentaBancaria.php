<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SociosCuentaBancaria extends Model
{
    protected $table = 'socios_cuentas_bancarias';
    protected $fillable = [
        'business_registration_id',
        'titular_cuenta',
        'dni',
        'banco_id',
        'tipo_cuenta_id',
        'numero_cuenta',
        'imagenes_cuenta'
    ];

    protected $casts = [
        'imagenes_cuenta' => 'array',
    ];

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }

    public function tipoCuenta()
    {
        return $this->belongsTo(TipoCuentaBancaria::class);
    }
}