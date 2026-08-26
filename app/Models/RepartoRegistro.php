<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'aprobado',
        'token_fmc',
        'documentos_adicionales',
        'pedidos_consecutivos',
        'nivel', // 1 principante, 2 intermedio , 3avanzado , 4 experto y 5 masteer
        'activo',
        'foto_perfil',
    ];

    protected $appends = [
        'foto_perfil_url',
    ];

    public function getFotoPerfilUrlAttribute()
    {
        return $this->foto_perfil
            ? url(Storage::disk('custom_public')->url($this->foto_perfil))
            : null;
    }

    protected $casts = [
        'mayor_edad' => 'boolean',
        'acepta_politica' => 'boolean',
        'estado' => 'boolean',
        'aprobado' => 'boolean',
        'documentos_adicionales' => 'array',
        'pedidos_consecutivos' => 'integer',
        'nivel' => 'integer',
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
    public function entregaCalendario() {
        return $this->hasMany(EntregaCalendario::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}