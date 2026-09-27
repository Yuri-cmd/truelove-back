<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Models\Establecimiento;
use App\Models\RepartoRegistro;
use App\Models\PedidoTracking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TicketController extends Controller
{
    public function generateTicket($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Obtener datos relacionados
        $cliente = Cliente::find($pedido->id_cliente);
        $clienteDireccion = $pedido->direccion
            ? null
            : ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $motorizado = RepartoRegistro::find($pedido->id_motorizado);
        $detalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
        
        // Calcular subtotal de productos (sin delivery)
        $subtotalProductos = $detalles->sum(function ($item) {
            return floatval($item->precio) * $item->cantidad;
        });
        
        $descuento = floatval($pedido->descuento ?? 0);
        $total = $subtotalProductos - $descuento;

        // Formatear fecha
        $fecha = Carbon::parse($pedido->created_at);
        $meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 
                  'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $fechaFormateada = $meses[$fecha->month - 1] . ' ' . str_pad($fecha->day, 2, '0', STR_PAD_LEFT) . ' del, ' . $fecha->year;
        $horaFormateada = $fecha->format('H:i');

        // Tipo de pedido
        $tipoPedido = $pedido->tipo_pedido == 0 ? 'DELIVERY' : 'RECOJO EN TIENDA';
        
        // Tipo de pago
        $tipoPago = $pedido->tipo_pago ?? 'EFECTIVO';

        // Logo de la marca embebido en base64: el ticket se genera con Pdf::loadView
        // (sin pasar por HTTP), así que un <img> con ruta local o URL pública no
        // siempre resuelve igual en todos los entornos; el base64 sí es fiable.
        $logoPath = public_path('images/logo-truelove.png');
        $logoBase64 = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $data = [
            'pedido' => $pedido,
            'cliente' => $cliente,
            'direccion' => $pedido->direccion ?? ($clienteDireccion?->direccion ?? ''),
            'local' => $local,
            'localName' => $local->nombre_establecimiento ?? 'TRUE LOVE',
            'logoBase64' => $logoBase64,
            'motorizado' => $motorizado,
            'detalles' => $detalles,
            'descuento' => $descuento,
            'total' => $total,
            'fecha' => $fechaFormateada,
            'hora' => $horaFormateada,
            'tipoPedido' => $tipoPedido,
            'tipoPago' => $tipoPago,
        ];

        $pdf = Pdf::loadView('tickets.pedido', $data);
        
        // Calcular altura dinámica basada en el contenido
        // (+30 respecto al diseño anterior: la etiqueta de marca en el header
        // y la línea de marca en el footer ocupan un poco más de espacio)
        $alturaBase = 365;
        $alturaPorProducto = 25;
        $alturaExtras = 0;
        
        if ($pedido->nota && $pedido->nota !== 'Sin nota') {
            $alturaExtras += 40;
        }
        if ($motorizado) {
            $alturaExtras += 30;
        }
        if ($descuento > 0) {
            $alturaExtras += 20;
        }
        
        $alturaTotal = $alturaBase + ($detalles->count() * $alturaPorProducto) + $alturaExtras;
        
        // Configurar tamaño de papel para ticket (80mm de ancho, altura dinámica)
        $pdf->setPaper([0, 0, 226.77, $alturaTotal], 'portrait'); // 80mm = 226.77 points

        return $pdf->stream("ticket-pedido-{$id}.pdf");
    }
}
