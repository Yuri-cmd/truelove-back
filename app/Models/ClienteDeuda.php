<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteDeuda extends Model
{
    public const PENDIENTE = 'pendiente';
    public const PAGADO = 'pagado';
    public const ANULADO = 'anulado';
    public const ESTADOS = [self::PENDIENTE, self::PAGADO, self::ANULADO];

    protected $table = 'cliente_deudas';

    protected $fillable = [
        'cliente_id', 'pedido_id', 'motorizado_id', 'monto', 'motivo', 'estado',
        'registrado_por', 'observaciones_admin', 'gestionada_por', 'gestionada_at',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'gestionada_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function motorizado()
    {
        return $this->belongsTo(RepartoRegistro::class, 'motorizado_id');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::PENDIENTE);
    }

    /** Total del pedido: productos y adicionales + delivery - descuento. */
    public static function totalDelPedido(Pedido $pedido): float
    {
        $productos = PedidoDetalle::where('pedido_id', $pedido->id)->get()
            ->sum(fn ($d) => (float) $d->precio * (float) $d->cantidad);

        return round(max(0, $productos + (float) $pedido->precio_delivery - (float) $pedido->descuento), 2);
    }

    /** Deudas pendientes de un cliente y su total (para bloquear pedidos nuevos). */
    public static function resumenPendiente(int $clienteId): array
    {
        $deudas = self::pendientes()
            ->where('cliente_id', $clienteId)
            ->where('monto', '>', 0)
            ->orderBy('id')
            ->get();

        $total = round((float) $deudas->sum('monto'), 2);

        return [
            'tiene_deuda' => $deudas->isNotEmpty() && $total > 0,
            'total_adeudado' => $total,
            'deudas' => $deudas->map(fn ($d) => [
                'id' => $d->id,
                'pedido_id' => $d->pedido_id,
                'monto' => number_format((float) $d->monto, 2, '.', ''),
                'motivo' => $d->motivo,
                'fecha' => $d->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
