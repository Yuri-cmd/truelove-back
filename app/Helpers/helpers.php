<?php

if (!function_exists('estadoPedido')) {
    function estadoPedido($estado)
    {
        switch ($estado) {
            case '0':
                return 'cancelado';
                break;
            case '1':
                return 'Pendiente';
                break;
            case '2':
                return 'El restaurante está preparando el pedido';
                break;
            case '3':
                return 'El resturante termino el pedido';
                break;
            case '4':
                return 'Motorizado acepto pedido';
                break;
            case '5':
                return 'Motorizado llego al restaurante';
                break;
            case '6':
                return 'Motorizado en camino';
                break;
            case '7':
                return 'Motorizado llego al domicilio';
                break;
            case '8':
                return 'Pedido entregado';
                break;
            default:
                # code...
                break;
        }
    }
}
