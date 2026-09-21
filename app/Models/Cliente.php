<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Cliente extends Authenticatable
{
    use HasFactory, HasApiTokens;
    protected $table = 'clientes';
    protected $fillable = [
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'genero',
        'email',
        'documento',
        'nacionalidad',
        'celular',
        'celular_whatsapp',
        'dni_photo',
        'selfie_photo',
        'foto_perfil',
        'password',
        'token_fmc',
    ];

    protected $hidden = [
        'password',
    ];
}
