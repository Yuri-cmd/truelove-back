<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCuentaBancaria extends Model
{
    use HasFactory;

    protected $table = 'tipos_cuenta_bancaria';
    protected $fillable = ['nombre'];
}

