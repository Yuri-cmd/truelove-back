<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negocio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo_negocio_id',
        'categoria_id',
        'user_id',
        'total_sucursales',
        'es_local_calle',
        'metodo_contacto',
        'telefono',
        'activo'
    ];

    public function tipoNegocio()
    {
        return $this->belongsTo(TipoNegocio::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sucursales()
    {
        return $this->hasMany(Sucursal::class);
    }
}