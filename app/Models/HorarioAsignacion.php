<?php
// app/Models/HorarioAsignacion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioAsignacion extends Model
{
    use HasFactory;

    protected $table = 'horario_asignaciones';
    
    protected $fillable = [
        'grupo_id',
        'motorizado_id'
    ];

    public function grupo()
    {
        return $this->belongsTo(HorarioGrupo::class, 'grupo_id');
    }

    public function motorizado()
    {
        return $this->belongsTo(RepartoRegistro::class, 'motorizado_id');
    }
}