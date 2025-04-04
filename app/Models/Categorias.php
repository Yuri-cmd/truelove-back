<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorias extends Model
{
    protected $table = 'categoria';
    protected $fillable = [
        'empresa_id',
        'nombre',
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
}
