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
        $clienteDireccion = ClienteDireccion::where('id_cliente', $pedido->id_cliente)->first();
        $local = Establecimiento::where('business_registration_id', $pedido->id_local)->first();
        $motorizado = RepartoRegistro::find($pedido->id_motorizado);
        $detalles = PedidoDetalle::where('pedido_id', $pedido->id)->get();
        
        // Calcular subtotal (sin delivery)
        $subtotal = $detalles->sum(function ($item) {
            return floatval($item->precio) * $item->cantidad;
        });
        
        $descuento = floatval($pedido->descuento ?? 0);
        $total = $subtotal - $descuento;

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

        $data = [
            'pedido' => $pedido,
            'cliente' => $cliente,
            'direccion' => $clienteDireccion->direccion ?? '',
            'local' => $local,
            'localName' => $local->nombre_establecimiento ?? 'TRUE LOVE',
            'motorizado' => $motorizado,
            'detalles' => $detalles,
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'total' => $total,
            'fecha' => $fechaFormateada,
            'hora' => $horaFormateada,
            'tipoPedido' => $tipoPedido,
            'tipoPago' => $tipoPago,
        ];

        $pdf = Pdf::loadView('tickets.pedido', $data);
        
        // Configurar tamaño de papel para ticket (80mm de ancho)
        $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm = 226.77 points

        return $pdf->stream("ticket-pedido-{$id}.pdf");
    }
}
