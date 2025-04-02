<?php

if (!function_exists('estadoPedido')) {
    function estadoPedido($estado)
    {
        switch ($estado) {
            case '0':
                return 'cancelado';
                break;
            case '1':
                return 'El restaurante está preparando el pedido';
                break;
            case '2':
                return 'El resturante termino el pedido';
                break;
            case '3':
                return 'Motorizado acepto pedido';
                break;
            case '4':
                return 'Motorizado llego al restaurant';
                break;
            case '5':
                return 'Motorizado en camino';
                break;
            case '6':
                return 'Motorizado llego al domicilio';
                break;
            case '7':
                return 'Pedido entregado';
                break;
            default:
                # code...
                break;
        }
    }
}
