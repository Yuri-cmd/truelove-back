<?php

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ClienteWebAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de autenticación de clientes para truelove-front (web)
|--------------------------------------------------------------------------
|
| Independientes del login de la app móvil (POST /login/cliente en
| ClienteController, que no emite token). Estas rutas sí emiten tokens
| Sanctum y son las que debe usar la web.
|
*/

Route::prefix('web/cliente')->group(function () {
    Route::post('login', [ClienteWebAuthController::class, 'login']);
    Route::post('google-login', [ClienteWebAuthController::class, 'googleLogin']);
    Route::post('register', [ClienteWebAuthController::class, 'register']);

    // Reutilizan el envío de código OTP ya existente para la app.
    Route::post('send-code', [ClienteController::class, 'sendCode']);
    // Sin límite, cualquiera podía pedir código para cualquier correo las
    // veces que quisiera (así llegó este reporte: alguien lo disparó varias
    // veces contra un cliente real sin que nada lo frenara).
    Route::post('forgot-password/send-code', [ClienteController::class, 'sendCodeNew'])->middleware('throttle:5,1');
    Route::post('forgot-password/reset', [ClienteWebAuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [ClienteWebAuthController::class, 'me']);
        Route::post('logout', [ClienteWebAuthController::class, 'logout']);
    });
});
