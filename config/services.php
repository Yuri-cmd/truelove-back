<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // WhatsApp Cloud API (Meta)
    'whatsapp' => [
        'token' => env('META_TOKEN'),
        'phone_number_id' => env('META_PHONE_NUMBER_ID'),
        'waba_id' => env('META_WABA_ID'),
        'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
        // Webhook: token que se escribe en Meta al verificar la URL, y secreto de la
        // app (opcional) para comprobar la firma X-Hub-Signature-256 de cada evento.
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
        // Tope de mensajes de WhatsApp por mes (control de gasto: ~US$0.03 por mensaje de
        // plantilla entregado). Al llegar a (tope - reserva) el back deja de llamar a la API y
        // responde ok sin enviar. La reserva cubre envíos simultáneos justo en el límite.
        'cuota_mensual' => env('WHATSAPP_CUOTA_MENSUAL', 250),
        'cuota_reserva' => env('WHATSAPP_CUOTA_RESERVA', 0),
        // Plantilla de Autenticación aprobada en Meta con la que se envía el código de verificación.
        'plantilla_codigo' => env('WHATSAPP_PLANTILLA_CODIGO', 'codigo_verificacion'),
        'plantilla_codigo_idioma' => env('WHATSAPP_PLANTILLA_CODIGO_IDIOMA', 'es'),
        // Exigir número validado para hacer pedidos. Las apps nuevas lo piden enviando exige_validacion;
        // con esta bandera en true se exige a todos (cuando ya no queden apps antiguas).
        'exigir_validacion' => env('WHATSAPP_EXIGIR_VALIDACION', false),
        // Límite de envíos por número de teléfono (evita que se pidan códigos en cadena)
        'limite_intervalo_seg' => env('WHATSAPP_LIMITE_INTERVALO_SEG', 60),
        'limite_por_hora' => env('WHATSAPP_LIMITE_POR_HORA', 3),
        'limite_por_dia' => env('WHATSAPP_LIMITE_POR_DIA', 6),
    ],

    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'apiperu' => [
        'token' => env('APIPERU_DEV_TOKEN'),
    ],

    'firebase_web' => [
        'project_id' => env('FIREBASE_WEB_PROJECT_ID', env('BIKER_FIREBASE_PROJECT_ID')),
    ],

    'biker_firebase' => [
        'project_id' => env('BIKER_FIREBASE_PROJECT_ID'),
        'private_key_id' => env('BIKER_FIREBASE_GOOGLE_PRIVATE_KEY_ID'),
        'private_key' => env('BIKER_FIREBASE_GOOGLE_PRIVATE_KEY'),
        'client_email' => env('BIKER_FIREBASE_GOOGLE_CLIENT_EMAIL'),
        'client_id' => env('BIKER_FIREBASE_GOOGLE_CLIENT_ID'),
    ],

];
