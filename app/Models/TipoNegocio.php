<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoNegocio extends Model
{
    use HasFactory;

    // Specify the correct table name
    protected $table = 'tipos_negocios';

    protected $fillable = ['nombre', 'slug', 'activo', 'image', 'orden'];

    public function categorias()
    {
        return $this->hasMany(Categoria::class);
    }

    public function negocios()
    {
        return $this->hasMany(Negocio::class);
    }
}