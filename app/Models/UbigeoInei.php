<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UbigeoInei extends Model
{
    use HasFactory;

    protected $table = 'ubigeo_inei';
    protected $primaryKey = 'id_ubigeo';
    public $timestamps = false;

    protected $fillable = [
        'id_ubigeo',
        'departamento',
        'provincia',
        'distrito',
        'nombre'
    ];

    /**
     * Obtener todos los departamentos únicos
     */
    public static function getDepartamentos()
    {
        return self::where('provincia', '00')
                  ->where('distrito', '00')
                  ->orderBy('nombre')
                  ->get();
    }

    /**
     * Obtener provincias por departamento
     */
    public static function getProvincias($departamentoId)
    {
        return self::where('departamento', $departamentoId)
                  ->where('provincia', '<>', '00')
                  ->where('distrito', '00')
                  ->orderBy('nombre')
                  ->get();
    }

    /**
     * Obtener distritos por provincia y departamento
     */
    public static function getDistritos($departamentoId, $provinciaId)
    {
        return self::where('departamento', $departamentoId)
                  ->where('provincia', $provinciaId)
                  ->where('distrito', '<>', '00')
                  ->orderBy('nombre')
                  ->get();
    }
}
