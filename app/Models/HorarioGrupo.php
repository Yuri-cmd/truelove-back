<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioGrupo extends Model
{
    use HasFactory;

    protected $table = 'horario_grupos';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'motorizado_individual_id',
        // 'rangos'
    ];

    // protected $casts = [
    //     'rangos' => 'array',
    // ];

    public function bloques()
    {
        return $this->hasMany(HorarioBloque::class, 'grupo_id')->orderBy('orden');
    }

    public function asignaciones()
    {
        return $this->hasMany(HorarioAsignacion::class, 'grupo_id');
    }

    public function motorizados()
    {
        return $this->belongsToMany(RepartoRegistro::class, 'horario_asignaciones', 'grupo_id', 'motorizado_id');
    }

    public function motorizadoIndividual()
    {
        return $this->belongsTo(RepartoRegistro::class, 'motorizado_individual_id');
    }
}