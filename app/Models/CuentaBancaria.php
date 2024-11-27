<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaBancaria extends Model
{
    use HasFactory;

    protected $table = 'cuentas_bancarias_reparto';
    
    protected $fillable = [
        'titular',
        'dni',
        'banco_id',
        'tipo_cuenta_id',
        'numero_cuenta',
        'url_imagen_cuenta'
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }

    public function tipoCuenta()
    {
        return $this->belongsTo(TipoCuentaBancaria::class);
    }
}