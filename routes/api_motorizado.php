<?php

use App\Http\Controllers\BikerController;
use App\Http\Controllers\DatosBancariosRepartoController;
use App\Http\Controllers\DatosPersonalesRepartoController;
use App\Http\Controllers\MotorizadoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoTrackingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RegistrationStatusController;
use App\Http\Controllers\RegistroVehiculoController;
use App\Http\Controllers\RepartoRegistroCompletoController;
use App\Http\Controllers\RepartoRegistroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la app Motorizado (biker) y del registro de repartidor
|--------------------------------------------------------------------------
| Movidas tal cual desde routes/api.php (mismo método, mismo URI, mismo
| middleware) — solo se reorganizó en qué archivo viven.
*/

// Self-servicio del motorizado ya autenticado (distinto de la gestión por el admin)
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('role:motorizado')->prefix('motorizado')->group(function () {
        Route::controller(MotorizadoController::class)->group(function () {
            Route::get('/perfil', 'perfil');
            Route::put('/actualizar', 'actualizar');
        });
    });
});

// Registro del repartidor (onboarding)
Route::post('/reparto/registro', [RepartoRegistroController::class, 'store']);
Route::post('/reparto/check-status', [RepartoRegistroController::class, 'checkStatus']);
Route::get('/reparto/{id}/status', [RepartoRegistroController::class, 'getRegistrationStatus']);
Route::post('/reparto/{id}/update-email', [RepartoRegistroController::class, 'updateEmail']);
Route::get('/reparto/{id}', [RepartoRegistroController::class, 'getRegistration']);

// Datos personales del registro de repartidor
Route::get('/departamentos', [DatosPersonalesRepartoController::class, 'obtenerDepartamentos']);
Route::get('/provincias/{departamentoId}', [DatosPersonalesRepartoController::class, 'obtenerProvincias']);
Route::get('/distritos/{departamentoId}/{provinciaId}', [DatosPersonalesRepartoController::class, 'obtenerDistritos']);
Route::post('/datos-personales', [DatosPersonalesRepartoController::class, 'guardar']);
Route::get('/datos-personales/{repartoRegistroId}', [DatosPersonalesRepartoController::class, 'show']);
Route::post('/datos-personales/{id}', [DatosPersonalesRepartoController::class, 'update']);
Route::put('/repartidores/info/{id}', [BikerController::class, 'updateInfo']);

// Datos bancarios del registro de repartidor
Route::get('/bancos', [DatosBancariosRepartoController::class, 'obtenerBancos']);
Route::get('/tipos-cuenta', [DatosBancariosRepartoController::class, 'obtenerTiposCuenta']);
Route::post('/cuenta-bancaria', [DatosBancariosRepartoController::class, 'guardarCuentaBancaria']);
Route::get('/cuenta-bancaria/{repartoRegistroId}', [DatosBancariosRepartoController::class, 'show']);
Route::post('/cuenta-bancaria/{id}', [DatosBancariosRepartoController::class, 'update']);

Route::post('/registro-vehiculo', [RegistroVehiculoController::class, 'guardar']);
Route::get('/registro-vehiculo/{repartoRegistroId}', [RegistroVehiculoController::class, 'mostrar']);

Route::post('/reparto/registro-completo', [RepartoRegistroCompletoController::class, 'registroCompleto']);
Route::post('/reparto/validate-document-email', [RepartoRegistroController::class, 'validateDocumentAndEmail']);

Route::post('/register/check-status', [RegistrationStatusController::class, 'checkStatus']);
Route::get('/register/{id}/status', [RegistrationStatusController::class, 'getRegistrationStatus']);
Route::post('/register/{id}/reset', [RegistrationStatusController::class, 'resetRegistration']);

// App biker (operación diaria del repartidor)
Route::post('biker/login', [BikerController::class, 'login']);
Route::get('biker/condiciones/{id}', [BikerController::class, 'condiciones']);
Route::get('biker/get/pedidos/{id}', [BikerController::class, 'getPedidos']);
Route::post('biker/iniciar_viaje', [PedidoController::class, 'iniciarViaje']);
Route::post('biker/location/update', [BikerController::class, 'updateLocation']);
Route::post('biker/update-token', [BikerController::class, 'updateToken']);
Route::post('biker/clear-token', [BikerController::class, 'clearToken']);
Route::get('/ratings/biker/{idUsuario}', [RatingController::class, 'getRatingsBiker']);
Route::get('/biker/perfil/{idUsuario}', [BikerController::class, 'getPerfl']);
Route::post('/biker/foto-perfil/{id}', [BikerController::class, 'actualizarFotoPerfil']);
// Notas de la casa: requieren el token del repartidor (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // El repartidor pide cancelar un pedido que no pudo entregar (la aprueba un admin)
    Route::post('/biker/pedidos/{idPedido}/solicitar-cancelacion', [App\Http\Controllers\PedidoCancelacionController::class, 'requestCancellationBiker']);
    Route::get('/biker/pedidos/{idPedido}/notas-entrega', [App\Http\Controllers\EntregaNotaController::class, 'index']);
    Route::post('/biker/pedidos/{idPedido}/notas-entrega', [App\Http\Controllers\EntregaNotaController::class, 'store']);
    Route::delete('/biker/notas-entrega/{id}', [App\Http\Controllers\EntregaNotaController::class, 'destroy']);
});
Route::post('/update-estado/pedido', [PedidoTrackingController::class, 'updateEstado']);
Route::post('/biker/alerta-auxilio', [PedidoController::class, 'mandarAlertaDeAuxilio']);
Route::post('/repartidor/estado', [BikerController::class, 'actualizarEstado']);
Route::post('/biker/sendCode', [BikerController::class, 'sendCode']);
Route::post('/biker/update-password', [BikerController::class, 'updatePassword']);
Route::get('/biker/viaje-activo/{idBiker}', [BikerController::class, 'viajeActivo']);
Route::get('/biker/viajes-activos/{idBiker}', [BikerController::class, 'viajesActivos']);
Route::get('/biker/viajes/{idBiker}', [BikerController::class, 'viajesListado']);
