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
                return 'Motorizado acepto pedido';
                break;
            case '2':
                return 'El resturante termino el pedido';
                break;
            case '3':
                return 'Motorizado llego al restaurante';
                break;
            case '4':
                return 'Motorizado en camino';
                break;
            case '5':
                return 'Motorizado llego al domicilio';
                break;
            case '6':
                return 'Pedido entregado';
                break;
            default:
                # code...
                break;
        }
    }
}
