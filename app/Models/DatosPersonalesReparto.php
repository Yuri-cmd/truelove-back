<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatosPersonalesReparto extends Model
{
    use HasFactory;

    protected $table = 'datos_personales';

    protected $fillable = [
        'reparto_registro_id',
        'fecha_nacimiento',
        'genero',
        'url_selfie',
        'ubigeo_id'  // Solo mantenemos el nuevo campo
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function repartoRegistro()
    {
        return $this->belongsTo(RepartoRegistro::class);
    }

    // Agregamos la nueva relación
    public function ubigeo()
    {
        return $this->belongsTo(UbigeoInei::class, 'ubigeo_id', 'id_ubigeo');
    }
    
    // Método para obtener el nombre del departamento
    public function getDepartamentoAttribute()
    {
        if ($this->ubigeo) {
            $departamentoId = $this->ubigeo->departamento;
            $departamento = UbigeoInei::where('departamento', $departamentoId)
                ->where('provincia', '00')
                ->where('distrito', '00')
                ->first();
            return $departamento ? $departamento->nombre : null;
        }
        return null;
    }
    
    // Método para obtener el nombre de la provincia
    public function getProvinciaAttribute()
    {
        if ($this->ubigeo) {
            $departamentoId = $this->ubigeo->departamento;
            $provinciaId = $this->ubigeo->provincia;
            $provincia = UbigeoInei::where('departamento', $departamentoId)
                ->where('provincia', $provinciaId)
                ->where('distrito', '00')
                ->first();
            return $provincia ? $provincia->nombre : null;
        }
        return null;
    }
    
    // Método para obtener el nombre del distrito
    public function getDistritoAttribute()
    {
        return $this->ubigeo ? $this->ubigeo->nombre : null;
    }
}

