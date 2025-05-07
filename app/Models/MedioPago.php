<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedioPago extends Model
{
    protected $table = 'medio_de_pagos';

    protected $fillable = [
        'nombre',
        'estado',
    ];
}
