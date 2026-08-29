<?php

namespace App\Services;

/**
 * ⚠️ PARCHE TEMPORAL (2026-08-28) — QUITAR cuando las nuevas versiones de las apps
 * `truelovesocio` y `truelovebiker` (que muestran el precio del adicional en su
 * propia columna, ver `PedidoProductosAgrupados`/`PedidoProductosList` en cada app)
 * estén publicadas y adoptadas por locales y motorizados.
 *
 * Motivo: las apps instaladas actualmente solo pintan `nombre` en la etiqueta del
 * adicional, sin mostrar su precio. Cuando un adicional NO es gratis (ej. "Salsa
 * acevichada" a S/4.00), el local/motorizado no se entera de por qué el total no
 * coincide con la suma de los productos "base". Mientras se publican las
 * actualizaciones, se antepone el precio al nombre aquí en el backend para que se
 * vea de inmediato sin necesitar actualizar la app. Esto NO cambia ningún cálculo
 * de totales — solo hace visible en el texto lo que ya se estaba cobrando bien.
 *
 * Una vez que locales y motorizados estén todos en las versiones nuevas, remover
 * este archivo y sus usos para volver a mandar el `nombre` tal cual está en la BD.
 */
class PedidoDetalleFormatter
{
    /**
     * Recibe una colección/lista de PedidoDetalle (o cualquier iterable de objetos
     * con `tipo`/`precio`/`nombre`) y le antepone el precio al nombre de cada
     * adicional pagado. Muta los objetos en memoria; no toca la base de datos.
     */
    public static function anotarPrecioAdicionales($pedidoDetalles)
    {
        foreach ($pedidoDetalles as $detalle) {
            if ($detalle->tipo === 'adicional' && (float) $detalle->precio > 0) {
                $detalle->nombre = $detalle->nombre . ' (+S/ ' . number_format((float) $detalle->precio, 2) . ')';
            }
        }
        return $pedidoDetalles;
    }
}
