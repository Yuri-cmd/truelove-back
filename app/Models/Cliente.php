<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;
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
}
