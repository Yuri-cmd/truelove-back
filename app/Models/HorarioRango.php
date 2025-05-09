<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioRango extends Model
{
    use HasFactory;

    protected $table = 'horario_rangos';
    
    protected $fillable = [
        'grupo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin'
    ];

    public function grupo()
    {
        return $this->belongsTo(HorarioGrupo::class, 'grupo_id');
    }
}