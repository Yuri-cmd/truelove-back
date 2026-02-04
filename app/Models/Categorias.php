<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorias extends Model
{
    protected $table = 'categoria';
    protected $fillable = [
        'nombre',
        'empresa_id',
        'hora_inicio', // Se mantienen por compatibilidad si es necesario
        'hora_fin',
        'horarios', // Nuevo campo
        'estado'
    ];

    protected $casts = [
        'horarios' => 'array', // Esto hace la magia de convertir JSON <-> Array
    ];

    // Relación inversa con Menu
    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'categoria_menu', 'categoria_id', 'menu_id');
    }

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);

    }
    // Relación inversa con Menu

}
