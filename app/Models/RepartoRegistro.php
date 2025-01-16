<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepartoRegistro extends Model
{
    use HasFactory;

    protected $fillable = [
        'departamento',
        'vehiculo',
        'tipo_documento',
        'nro_documento',
        'nombres',
        'apellidos',
        'celular',
        'email',
        'mayor_edad',
        'acepta_politica',
        'documento_imagen_frente',
        'documento_imagen_reverso',
        'estado',
        'aprobado'
    ];

    protected $casts = [
        'mayor_edad' => 'boolean',
        'acepta_politica' => 'boolean',
        'estado' => 'boolean',
        'aprobado' => 'boolean'
    ];

    public function datosPersonales()
    {
        return $this->hasOne(DatosPersonalesReparto::class);
    }

    public function datosBancarios()
    {
        return $this->hasOne(CuentaBancariaReparto::class);
    }

    public function registroVehiculo()
    {
        return $this->hasOne(RegistroVehiculo::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}