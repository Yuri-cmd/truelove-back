<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adicional extends Model
{
    use HasFactory;

    protected $table = 'adicionales';

    protected $fillable = [
        'menu_id',
        'empresa_id',
        'titulo',
        'descripcion',
        'foto',
        'precio',
        'status',
    ];

    /**
     * Relación: El adicional pertenece a un menú (producto)
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * Relación: El adicional pertenece a una empresa
     */
    public function empresa()
    {
        return $this->belongsTo(BusinessRegistration::class, 'empresa_id');
    }
}
