<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisarDatos extends Model
{
    protected $table = 'revisar_datos';
    
    protected $fillable = [
        'negocio_id',
        'establecimiento_id',
        'datos_clave_negocio_id',
        'datos_bancarios_id',
        'terminos_aceptados',
        'fecha_revision'
    ];

    protected $casts = [
        'terminos_aceptados' => 'boolean',
        'fecha_revision' => 'datetime'
    ];

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function datosClaveNegocio(): BelongsTo
    {
        return $this->belongsTo(DatosClaveNegocio::class);
    }

    public function datosBancarios(): BelongsTo
    {
        return $this->belongsTo(DatosBancarios::class);
    }
}