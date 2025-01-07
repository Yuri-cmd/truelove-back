<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaBancariaReparto extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias_reparto';
    protected $fillable = [
        'reparto_registro_id',
        'titular',
        'dni',
        'banco_id',
        'tipo_cuenta_id',
        'numero_cuenta',
        'url_imagen_cuenta'
    ];

    public function repartoRegistro()
    {
        return $this->belongsTo(RepartoRegistro::class);
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