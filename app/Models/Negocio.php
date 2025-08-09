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
        'activo',
        'business_registration_id',
        'tipo_pago_digital', // 0 ninguno ,1 yapé y 2 plin
        'numero_pago_digital' // numero de pago digital segun el tipo
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

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}