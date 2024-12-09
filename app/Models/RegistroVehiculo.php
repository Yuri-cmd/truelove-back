<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistroVehiculo extends Model
{
    protected $table = 'registros_vehiculos';

    protected $fillable = [
        'placa',
        'licencia_conducir',
        'seguro',
        'tarjeta_propiedad',
        'imagen_placa',
        'imagen_licencia',
        'imagen_seguro',
        'imagen_tarjeta_propiedad'
    ];
}

