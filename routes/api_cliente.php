<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DescuentoClienteController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\LocalesController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoTrackingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TipoNegocioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la app Cliente (navegación, pedidos, perfil)
|--------------------------------------------------------------------------
| Movidas tal cual desde routes/api.php (mismo método, mismo URI, mismo
| middleware) — solo se reorganizó en qué archivo viven.
*/

Route::post('/confirmar-pedido', [PedidoController::class, 'store']);
Route::get('/pedidos/{id}', [PedidoTrackingController::class, 'obtenerEstado']);
Route::post('/cancelar-pedido', [PedidoController::class, 'cancelarPedido']);
Route::get('motorcycle-location/{idPedido}', [LocationController::class, 'fetchMotorcycleLocation']);

Route::get('local-esta-abierto/{idLocal}', [NegocioController::class, 'localEstaAbierto']);

// rutas app clientes
Route::post('/send-code', [ClienteController::class, 'sendCode']);
Route::post('/profile', [ClienteController::class, 'store']);
Route::post('/get-dni', [ClienteController::class, 'getDni']);
Route::post('/upload-photos', [ClienteController::class, 'uploadPhotos']);
Route::post('/update-profile', [ClienteController::class, 'actualizarInfoCliente']);
Route::post('/send-code-phone', [ClienteController::class, 'sendCodePhone'])->middleware('throttle:10,1'); // 10 por minuto por IP
Route::get('/send-code-phone/{id}/estado', [ClienteController::class, 'estadoEnvioCodigo'])->middleware('throttle:60,1');
Route::post('/clientes/{idCliente}/validar-numero', [ClienteController::class, 'validarNumero'])->middleware('throttle:20,1');
Route::get('/get/tipo/negocio', [TipoNegocioController::class, 'getAll']);
Route::get('/get/locales/top/{id?}', [LocalesController::class, 'getLocalesTop']);
Route::get('/get/local/{id}', [LocalesController::class, 'getLocalById']);
Route::get('/get/local/{id}/entrega/{idCliente}', [LocalesController::class, 'getEntregaLocal']);
Route::get('/get/locales/{id}/{category?}', [LocalesController::class, 'getLocales']);
Route::get('/busqueda/locales/{id}/{term?}', [LocalesController::class, 'searchLocales']);
Route::post('/favoritos/toggle', [FavoritoController::class, 'toggle']);
Route::get('/favoritos/{idCliente}/empresa/{idEmpresa}', [FavoritoController::class, 'porEmpresa']);
Route::get('/favoritos/{idCliente}', [FavoritoController::class, 'porCliente']);
Route::get('/listar/menus/categoria/{empresa_id}', [MenuController::class, 'getMenuCategoria']);
Route::get('/customer-local-location/{idPedido}', [PedidoController::class, 'getLocalYcustomerPosition']);
Route::post('/pedido/ubicacion-cliente', [PedidoController::class, 'actualizarUbicacionCliente']);
Route::get('/pedido/{idPedido}/ubicacion-cliente', [PedidoController::class, 'obtenerUbicacionCliente']);
Route::post('/login/cliente', [ClienteController::class, 'login']);
Route::get('pedidos/cliente/{idCliente}', [PedidoController::class, 'getPedidosCliente']);
Route::get('notificaciones/cliente/{idCliente}', [App\Http\Controllers\NotificationTrackingController::class, 'misNotificaciones']);
Route::post('notificaciones/cliente/{idCliente}/marcar-todas-leidas', [App\Http\Controllers\NotificationTrackingController::class, 'marcarTodasLeidas']);
Route::post('/ratings', [RatingController::class, 'store']);  // Guardar calificación
Route::get('/ratings/{id_pedido}', [RatingController::class, 'getRatings']);
Route::get('/getMotorizado/{idPedido}', [PedidoController::class, 'getMotorizado']);
Route::get('/getMotorizadoInfo/{idPedido}', [PedidoController::class, 'getMotorizadoInfo']);
Route::get('/getPerfil/{idCliente}', [ClienteController::class, 'getPerfil']);
Route::post('/enviar-correo-pedido-entregado', [PedidoController::class, 'enviarCorreoPedidoEntregado']);
Route::get('/get/pedidos/{id}', [PedidoController::class, 'getPedido']);
Route::post('/update-info-cliente', [ClienteController::class, 'updateProfile']);
Route::post('/update-direccion', [ClienteController::class, 'actualizarDireccion']);
Route::post('/perfil/foto', [ClienteController::class, 'actualizarFotoPerfil']);
Route::post('/delete-account-app', [ClienteController::class, 'deleteAccountApp']);

// Catálogo de lectura pública (promociones/banners/medios de pago — el CRUD de gestión
// queda en api_socio.php)
Route::get('get/medios/pago/{idEmpresa?}', [App\Http\Controllers\MedioPagoController::class, 'index']);
Route::get('/promociones', [App\Http\Controllers\PromocionController::class, 'index']);
Route::get('/promociones/{id}', [App\Http\Controllers\PromocionController::class, 'show']);
Route::get('/banners', [App\Http\Controllers\BannerController::class, 'index']);
Route::get('/banners/{id}', [App\Http\Controllers\BannerController::class, 'show']);

Route::post('/cliente/sendCode', [ClienteController::class, 'sendCodeNew']);
Route::post('/cliente/update-password', [ClienteController::class, 'updatePassword']);
Route::get('/cliente/repetir/orden/{idPedido}', [PedidoController::class, 'repetirOrden']);
Route::get('/pedidos/{id}/verificar-confirmacion', [PedidoController::class, 'verificarConfirmacion']);
Route::put('socio/update/verificar/confirmacion/{idPedido}', [PedidoController::class, 'updateVerificarConfirmacion']);
Route::get('get/precio/delivery/{idLocal}/{idCliente}', [PedidoController::class, 'calcularPrecioDelivery']);
Route::get('validar/cupon/descuento/{code}/{idCliente}', [DescuentoClienteController::class, 'validarCodeDescuento']);
Route::post('cliente/update-token', [ClienteController::class, 'updateToken']);
Route::get('socio/entrega/documento/{idLocal}', [App\Http\Controllers\SocioController::class, 'socioEntregaDocumento']);
Route::post('upload-payment-proof', [PedidoController::class, 'uploadPaymentProof']);

// Rutas para eliminación de cuenta de cliente
Route::post('cliente/buscar-documento', [ClienteController::class, 'buscarPorDocumento']);
Route::post('cliente/solicitar-eliminacion', [ClienteController::class, 'solicitarEliminacionCuenta']);
Route::post('cliente/verificar-codigo-eliminacion', [ClienteController::class, 'verificarCodigoEliminacion']);

// chat (el cliente inicia/lee el chat; storeMotorizado lo usa la app del repartidor)
Route::get('/chats/{pedidoId}', [ChatController::class, 'index']);
Route::post('/chats/storeCliente', [ChatController::class, 'storeCliente']);
Route::post('/chats/storeMotorizado', [ChatController::class, 'storeMotorizado']);

// Estado de cuenta del cliente (deudas pendientes): la app lo consulta antes de pedir
Route::get('/clientes/{idCliente}/deudas', [App\Http\Controllers\ClienteDeudaController::class, 'estadoCuenta']);

// Múltiples direcciones del cliente (app de clientes nueva). La activa es la que usa todo lo demás.
Route::get('/clientes/{idCliente}/direcciones', [App\Http\Controllers\ClienteDireccionController::class, 'index']);
Route::post('/clientes/{idCliente}/direcciones', [App\Http\Controllers\ClienteDireccionController::class, 'store']);
Route::put('/clientes/{idCliente}/direcciones/{id}', [App\Http\Controllers\ClienteDireccionController::class, 'update']);
Route::delete('/clientes/{idCliente}/direcciones/{id}', [App\Http\Controllers\ClienteDireccionController::class, 'destroy']);
Route::post('/clientes/{idCliente}/direcciones/{id}/activar', [App\Http\Controllers\ClienteDireccionController::class, 'activar']);

// Formulario de soporte de la app
Route::post('/soporte/enviar', [App\Http\Controllers\SoporteController::class, 'enviarConsulta']);
