<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;
    protected $table = 'menu';
    protected $fillable = [
        'titulo',
        'descripcion',
        'foto',
        'precio',
        'status',
        'empresa_id',
    ];

    // Relación muchos a muchos con Categorias a través de la tabla pivot CategoriaMenu
    public function categorias()
    {
        return $this->belongsToMany(Categorias::class, 'categoria_menu', 'menu_id', 'categoria_id');

    }
    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);

    }
}
