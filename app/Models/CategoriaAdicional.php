<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaAdicional extends Model
{
    use HasFactory;

    protected $table = 'categoria_adicional';

    protected $fillable = [
        'empresa_id',
        'nombre',
    ];

    //relacion con el modelo 
    public function empresa()
    {
        return $this->belongsTo(BusinessRegistration::class, 'empresa_id');
    }

    public function adicionales()
    {
        return $this->hasMany(Adicional::class, 'categoria_adicional_id');
    }
}