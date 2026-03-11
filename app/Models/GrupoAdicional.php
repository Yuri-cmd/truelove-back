<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupoAdicional extends Model
{
    use HasFactory;

    protected $table = 'grupo_adicionales';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'minimo',
        'maximo',
        'estado',
        'orden'
    ];

    // Relación con los platos del menú
    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_grupos', 'grupo_id', 'menu_id')
                    ->withPivot('orden');
    }

    // Relación con los items adicionales (la biblioteca)
    public function items()
    {
        return $this->belongsToMany(Adicional::class, 'grupo_adicional_items', 'grupo_id', 'adicional_id')
                    ->withPivot('precio', 'orden')
                    ->withTimestamps();
    }
}