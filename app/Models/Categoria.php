<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'slug', 'tipo_negocio_id', 'activo'];

    public function tipoNegocio()
    {
        return $this->belongsTo(TipoNegocio::class);
    }

    public function negocios()
    {
        return $this->hasMany(Negocio::class);
    }
}