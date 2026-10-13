<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PedidoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas compartidas / sin un solo dueño claro
|--------------------------------------------------------------------------
| Todo lo que es exclusivo de una app vive en su propio archivo (requerido
| más abajo). Acá solo queda lo que varias apps consultan por igual, o lo
| que no tiene todavía un dueño obvio.
*/

Route::post('/notifications/update-status', [App\Http\Controllers\NotificationTrackingController::class, 'updateStatus']);

Route::get('/app-version/{app_name}', [AppVersionController::class, 'getVersion']);

Route::post('/error-logs', [App\Http\Controllers\ErrorLogController::class, 'store']);

// Webhook de WhatsApp Cloud (Meta): la URL se registra en el panel de Meta
Route::get('/webhooks/whatsapp', [App\Http\Controllers\WhatsappWebhookController::class, 'verificar']);
Route::post('/webhooks/whatsapp', [App\Http\Controllers\WhatsappWebhookController::class, 'recibir']);

// Rutas de autenticación y verificación del formulario principal y envio del correo
Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/verify', 'verify');
    Route::post('/resend-code', 'resendCode');
    Route::post('/register/{id}/update-email', 'updateEmail');
    Route::get('/register/{id}', 'getRegistration');
});

Route::post('/prueba-notificacion', [PedidoController::class, 'pruebaNoticacion']);
Route::post('/test-live-activity', [PedidoController::class, 'testLiveActivity']);
Route::get('prueba', [PedidoController::class, 'prueba']);

Route::get('landing/stats', [LandingController::class, 'stats']);

// Consulta pública de DNI/RUC (usada por formularios de registro antes de tener sesión:
// socio, motorizado, datos de negocio). Centraliza la consulta en el backend en vez de
// que el frontend llame directo a la API externa.
Route::get('/documento/dni/{numero}', [DocumentoController::class, 'dni']);
Route::get('/documento/ruc/{numero}', [DocumentoController::class, 'ruc']);

// Rutas de autenticación de clientes para la web (truelove-front)
require __DIR__.'/api_web_cliente.php';

// Rutas separadas por app/actor (ver carpeta routes/) — mismos método/URI/middleware
// que antes, solo reorganizadas para no tener todo en un único archivo de 700+ líneas.
require __DIR__.'/api_admin.php';
require __DIR__.'/api_cliente.php';
require __DIR__.'/api_motorizado.php';
require __DIR__.'/api_socio.php';
