<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappLog extends Model
{
    protected $table = 'whatsapp_logs';

    protected $guarded = [];

    protected $casts = [
        'cerrado_en' => 'datetime',
    ];

    public const SALIENTE = 'saliente';
    public const ENTRANTE = 'entrante';

    /** Estados finales: el mensaje ya no cambiará. */
    public const ESTADOS_FINALES = ['leido', 'fallido', 'omitido_cuota', 'sin_confirmacion', 'recibido'];

    /** Orden de avance de un envío (un estado nunca retrocede). */
    public const ORDEN = ['pendiente' => 0, 'enviado' => 1, 'entregado' => 2, 'leido' => 3];

    /** Envíos de este mes que sí gastan cuota (los omitidos por cuota o fallidos antes de salir no cuentan). */
    public function scopeConsumenCuotaEsteMes($query)
    {
        return $query->where('direccion', self::SALIENTE)
            ->whereNotNull('message_id')
            ->where('created_at', '>=', now()->startOfMonth());
    }
}
