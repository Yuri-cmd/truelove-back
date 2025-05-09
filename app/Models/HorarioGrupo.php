<?php
// app/Models/HorarioGrupo.php
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
        'rangos'
    ];

    protected $casts = [
        'rangos' => 'array', // Esto convierte automáticamente JSON a array y viceversa
    ];

    public function asignaciones()
    {
        return $this->hasMany(HorarioAsignacion::class, 'grupo_id');
    }

    public function motorizados()
    {
        return $this->belongsToMany(RepartoRegistro::class, 'horario_asignaciones', 'grupo_id', 'motorizado_id');
    }
}