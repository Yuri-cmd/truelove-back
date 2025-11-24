<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRegistration extends Model
{
    protected $fillable = [
        'documentType',
        'documentNumber',
        'name',
        'lastName',
        'businessType',
        'phone',
        'email',
        'verification_code',
        'email_verified_at',
        'estado',
        'aprobado',
        'posToDriver',
        'entrega_documento_venta',
        'activo',
        'token_fmc',
        'cuota_socio_id',
        'fecha_asignacion_cuota'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'estado' => 'integer',
        'aprobado' => 'boolean',
       'posToDriver' => 'integer', // puede tener valores 0 (No facilitar POS), 1 (POS estilos) y 2 (POS visa) y 3 envia ambos
        'activo' => 'boolean',
       'entrega_documento_venta' => 'integer', //  valores 0 (No emite documentos de venta) , 1 emite boleta ,2 emite factura , 3 emite ambos
        'fecha_asignacion_cuota' => 'datetime'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function negocio()
    {
        return $this->hasOne(Negocio::class);
    }

    public function establecimiento()
    {
        return $this->hasOne(Establecimiento::class);
    }

    public function datosClaveNegocio()
    {
        return $this->hasOne(DatosClaveNegocio::class);
    }

    public function datosBancarios()
    {
        return $this->hasOne(DatosBancarios::class);
    }
    public function cuentaBancaria()
    {
        return $this->hasOne(SociosCuentaBancaria::class);
    }

    public function revisarDatos()
    {
        return $this->hasOne(RevisarDatos::class);
    }

    public function perfil()
    {
        return $this->hasOne(PerfilNegocio::class);
    }

    // Relación con cuota asignada
    public function cuotaAsignada()
    {
        return $this->belongsTo(CuotaSocio::class, 'cuota_socio_id');
    }

    // Relación con períodos de cuota
    public function periodosCuota()
    {
        return $this->hasMany(PeriodoCuotaSocio::class, 'socio_id');
    }

    // Scope para socios con cuota asignada
    public function scopeConCuota($query)
    {
        return $query->whereNotNull('cuota_socio_id');
    }

    // Scope para socios sin cuota asignada
    public function scopeSinCuota($query)
    {
        return $query->whereNull('cuota_socio_id');
    }

    // Verificar si tiene cuota asignada
    public function tieneCuotaAsignada()
    {
        return !is_null($this->cuota_socio_id);
    }

    // Verificar si tiene períodos vencidos
    public function tienePeriodosVencidos()
    {
        return $this->periodosCuota()->vencidos()->exists();
    }

    // Obtener período actual
    public function periodoActual()
    {
        return $this->periodosCuota()->actual()->first();
    }

    // Obtener períodos próximos a vencer (5 días)
    public function periodosProximosAVencer()
    {
        return $this->periodosCuota()->proximosAVencer(5)->get();
    }
}