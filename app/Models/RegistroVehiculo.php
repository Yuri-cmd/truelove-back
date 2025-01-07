<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroVehiculo extends Model
{
    use HasFactory;
    protected $table = 'registros_vehiculos';
    protected $fillable = [
        'reparto_registro_id',
        'placa',
        'licencia_conducir',
        'seguro',
        'tarjeta_propiedad',
        'imagen_placa',
        'imagen_licencia',
        'imagen_seguro',
        'imagen_tarjeta_propiedad'
    ];

    public function repartoRegistro()
    {
        return $this->belongsTo(RepartoRegistro::class);
    }
}