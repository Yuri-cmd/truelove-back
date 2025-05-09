<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerfilNegocio extends Model
{
    use HasFactory;

    protected $table = 'perfiles_negocio';

    protected $fillable = [
        'business_registration_id',
        'ruta_logo',
        'foto_perfil',
        'banner'
    ];

    public function horarios()
    {
        return $this->hasMany(HorarioNegocio::class, 'perfil_negocio_id');
    }

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}