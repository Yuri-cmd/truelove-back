<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket Pedido #{{ $pedido->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            width: 80mm;
            padding: 5mm;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
        }

        .header .local-name {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .pedido-numero {
            text-align: center;
            margin-bottom: 12px;
        }

        .pedido-numero .numero {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px 0;
        }

        .info-table {
            width: 100%;
            font-size: 10px;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2px 0;
        }

        .info-table .label {
            font-weight: bold;
            width: 35%;
        }

        .separator {
            border-top: 1px solid #000;
            margin: 8px 0;
            text-align: center;
            position: relative;
        }

        .separator span {
            background: #fff;
            padding: 0 8px;
            position: relative;
            top: -8px;
            font-size: 9px;
        }

        .productos-table {
            width: 100%;
            font-size: 10px;
            margin-bottom: 8px;
            border-collapse: collapse;
        }

        .productos-table th {
            text-align: left;
            padding: 4px 0;
            font-weight: bold;
            border-bottom: 1px solid #000;
        }

        .productos-table th:nth-child(3),
        .productos-table th:nth-child(4) {
            text-align: right;
        }

        .productos-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .productos-table td:nth-child(3),
        .productos-table td:nth-child(4) {
            text-align: right;
        }

        .separator-line {
            border-top: 1px solid #000;
            margin: 8px 0;
        }

        .totales-table {
            width: 100%;
            font-size: 10px;
            margin-bottom: 8px;
        }

        .totales-table td {
            padding: 2px 0;
        }

        .totales-table .total-label {
            text-align: right;
            font-weight: bold;
        }

        .totales-table .total-value {
            text-align: right;
            width: 80px;
        }

        .totales-table .total-final .total-label,
        .totales-table .total-final .total-value {
            font-size: 12px;
            padding: 4px 0;
        }

        .motorizado {
            font-size: 10px;
            margin-bottom: 8px;
        }

        .motorizado .label {
            font-weight: bold;
        }

        .observaciones {
            font-size: 10px;
            margin-bottom: 8px;
        }

        .observaciones .label {
            font-weight: bold;
        }

        .footer {
            text-align: center;
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px dashed #999;
        }

        .footer .gracias {
            font-size: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="local-name">{{ $localName }}</div>
    </div>

    <!-- Numero de pedido -->
    <div class="pedido-numero">
        <div class="numero">PEDIDO N° {{ $pedido->id }}</div>
    </div>

    <!-- Informacion del pedido -->
    <table class="info-table">
        <tr>
            <td class="label">Fecha:</td>
            <td>{{ $fecha }}</td>
        </tr>
        <tr>
            <td class="label">Hora:</td>
            <td>{{ $hora }}</td>
        </tr>
        <tr>
            <td class="label">Cliente:</td>
            <td>{{ $cliente->nombre ?? '' }} {{ $cliente->apellido ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Telefono:</td>
            <td>{{ $cliente->celular ?? '' }}</td>
        </tr>
        @if($pedido->tipo_pedido == 0 && $direccion)
        <tr>
            <td class="label" style="vertical-align: top;">Direccion:</td>
            <td>{{ $direccion }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Tipo:</td>
            <td>{{ $tipoPedido }}</td>
        </tr>
        <tr>
            <td class="label">Forma de Pago:</td>
            <td>{{ $tipoPago }}</td>
        </tr>
    </table>

    <!-- Separador -->
    <div class="separator">
        <span>= =</span>
    </div>

    <!-- Tabla de productos -->
    <table class="productos-table">
        <thead>
            <tr>
                <th>CANT</th>
                <th>DESCRIPCION</th>
                <th>P.UNIT</th>
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
            <tr>
                <td>{{ $detalle->cantidad }}</td>
                <td>
                    {{ $detalle->nombre }}
                    @if($detalle->tipo === 'adicional')
                    (Adic.)
                    @endif
                </td>
                <td>{{ number_format(floatval($detalle->precio), 2) }}</td>
                <td>{{ number_format(floatval($detalle->precio) * $detalle->cantidad, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Separador -->
    <div class="separator-line"></div>

    <!-- Totales -->
    <table class="totales-table">
        @if($descuento > 0)
        <tr>
            <td class="total-label">Descuento:</td>
            <td class="total-value">-{{ number_format($descuento, 2) }}</td>
        </tr>
        @endif
        <tr class="total-final">
            <td class="total-label">Total:</td>
            <td class="total-value">S/ {{ number_format($total, 2) }}</td>
        </tr>
    </table>

    <!-- Motorizado -->
    @if($motorizado)
    <div class="motorizado">
        <span class="label">Motorizado: </span>
        <span>{{ $motorizado->nombres }} {{ $motorizado->apellidos }}</span>
    </div>
    @endif

    <!-- Observaciones -->
    @if($pedido->nota && $pedido->nota !== 'Sin nota')
    <div class="observaciones">
        <span class="label">Observaciones: </span>
        <span>{{ $pedido->nota }}</span>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div class="gracias">Gracias por su preferencia!</div>
    </div>
</body>
</html>
