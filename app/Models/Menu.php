<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo', 'descripcion', 'foto', 'precio', 'status', 'empresa_id',
    ];

    // Relación con la tabla CategoriaMenu
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'categoria_menu');
    }
}
