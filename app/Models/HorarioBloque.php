<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioBloque extends Model
{
    use HasFactory;

    protected $table = 'horario_bloques';
    
    protected $fillable = [
        'grupo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'tipo',
        'descripcion',
        'color',
        'orden'
    ];

    protected $casts = [
        'dia_semana' => 'array',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
    ];

    public function grupo()
    {
        return $this->belongsTo(HorarioGrupo::class, 'grupo_id');
    }
}