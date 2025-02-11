<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adicional extends Model
{
    use HasFactory;

    protected $table ='adicionales'; //nombre de la tabla

    protected $fillable = [
        'empresa_id', //empresa_id es el id de bussiness_registration
        'categoria_adicional_id',
        'titulo',
        'descripcion',
        'foto',
        'precio',
        'status',
    ];

    public function empresa() {
        //adicional pertenece a una empresa , bussiness_registration
        return $this->belongsTo(BusinessRegistration::class,'empresa_id');

    }

    public function categoriaAdicional() {
        return $this->belongsTo(CategoriaAdicional::class,'categoria_adicional_id');
    }
}
