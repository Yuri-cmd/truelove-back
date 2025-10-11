<?php

if (!function_exists('mensajeNotificacionPedido')) {
    /**
     * Genera el cuerpo del mensaje de notificación según el estado y destinatario
     * @param int|string $estado
     * @param int|string $pedidoId
     * @param string $para 'local' o 'cliente'
     * @return string
     */
    function mensajeNotificacionPedido($estado, $pedidoId, $para = 'local')
    {
        switch ((string)$estado) {
            case '1':
                return $para === 'local'
                    ? "El pedido #$pedidoId ya está disponible para procesar."
                    : "Tu pedido #$pedidoId fue recibido y está pendiente de confirmación.";
            case '2':
                return $para === 'local'
                    ? "El pedido #$pedidoId está siendo preparado."
                    : "El restaurante está preparando tu pedido #$pedidoId.";
            case '3':
                return $para === 'local'
                    ? "El pedido #$pedidoId está listo para ser recogido."
                    : "El restaurante terminó de preparar tu pedido #$pedidoId.";
            case '4':
                return $para === 'local'
                    ? "Un motorizado aceptó el pedido #$pedidoId."
                    : "Un motorizado aceptó tu pedido #$pedidoId.";
            case '5':
                return $para === 'local'
                    ? "El motorizado llegó al restaurante para el pedido #$pedidoId."
                    : "El motorizado llegó al restaurante para tu pedido #$pedidoId.";
            case '6':
                return $para === 'local'
                    ? "El motorizado está en camino con el pedido #$pedidoId."
                    : "El motorizado está en camino con tu pedido #$pedidoId.";
            case '7':
                return $para === 'local'
                    ? "El motorizado llegó al domicilio para el pedido #$pedidoId."
                    : "El motorizado llegó a tu domicilio con el pedido #$pedidoId.";
            case '8':
                return $para === 'local'
                    ? "El pedido #$pedidoId fue entregado al cliente."
                    : "¡Tu pedido #$pedidoId fue entregado! ¡Buen provecho!";
            case '0':
                return $para === 'local'
                    ? "El pedido #$pedidoId fue cancelado."
                    : "Tu pedido #$pedidoId ha sido cancelado por el restaurante.";
            default:
                return $para === 'local'
                    ? "El pedido #$pedidoId ha cambiado de estado."
                    : "Tu pedido #$pedidoId ha cambiado de estado.";
        }
    }
}

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
            case '9':
                return 'Pedido listo para recoger';
                break;
            default:
                # code...
                break;
        }
    }
}

if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber($phoneNumber)
    {
        // Elimina espacios, guiones y paréntesis si existen
        $numero_local = preg_replace('/[\s\-\(\)]+/', '', $phoneNumber);

        // Elimina el prefijo +51 o 51
        $numero_local = preg_replace('/^\+?51/', '', $numero_local);

        // Toma solo los últimos 9 dígitos
        return substr($numero_local, -9);
    }
}
