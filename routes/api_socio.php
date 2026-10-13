<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AdicionalController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CuotaSocioController;
use App\Http\Controllers\DatosBancariosController;
use App\Http\Controllers\DatosClaveNegocioController;
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\GrupoAdicionalController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\PedidoCancelacionController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PerfilNegocioController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RevisarDatosController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\SocioPromocionController;
use App\Http\Controllers\SociosCuentaBancariaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la app/panel Socio (comercio) — registro de negocio y operación
|--------------------------------------------------------------------------
| Movidas tal cual desde routes/api.php (mismo método, mismo URI, mismo
| middleware) — solo se reorganizó en qué archivo viven.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('role:negocio')->group(function () {
        Route::put('/socio/personal-info', [SocioController::class, 'actualizarInformaciónPersonal']);
        Route::put('/socio/business-info', [SocioController::class, 'actualizarInformaciónNegocio']);
        Route::put('/socio/establishment', [SocioController::class, 'actualizarEstablecimiento']);
        Route::put('/socio/business-key-info', [SocioController::class, 'actualizarDatosClaveNegocio']);
        Route::put('/socio/bank-data', [SocioController::class, 'actualizarDatosBancarios']);
        Route::post('/socio/bank-account', [SocioController::class, 'actualizarCuentaBancaria']);
        Route::put('/socio/change-password', [SocioController::class, 'changePassword']);
        Route::get('/socio/perfil', [SocioController::class, 'getAuthenticatedSocioDetails']);

        Route::controller(SocioController::class)->group(function () {
            Route::get('/socio/pedidos/{id}', 'getPedidos');
            Route::post('/socio/pedidos/update-estado/{id}', 'updateEstadoPedido');
            Route::post('/socio/update-token-web', 'updateTokenWeb');
        });

        Route::controller(SocioPromocionController::class)->prefix('socio/promociones')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update'); // el front manda _method=PUT via FormData, igual que /promociones
            Route::delete('/{id}', 'destroy');
        });
    });
});

// Rutas de cuotas para socios (acepta Bearer token O X-Socio-Id header)
Route::middleware('auth.socio.flexible')->group(function () {
    Route::get('/socio/cuota-activa', [CuotaSocioController::class, 'getCuotaActiva']);
    Route::post('/socio/subir-comprobante', [CuotaSocioController::class, 'subirComprobante']);
    Route::get('/socio/mis-pagos', [CuotaSocioController::class, 'misPagos']);
    Route::get('/socio/mi-periodo-actual', [CuotaSocioController::class, 'miPeriodoActual']);
    Route::get('/socio/mis-periodos', [CuotaSocioController::class, 'misPeriodos']);
    Route::get('/socio/pedidos-periodo/{periodoId}', [CuotaSocioController::class, 'pedidosPeriodo']);
    Route::get('/socio/verificar-acceso', [CuotaSocioController::class, 'verificarAcceso']);
    Route::post('/socio/subir-comprobante-periodo', [CuotaSocioController::class, 'subirComprobantePeriodo']);
});

// Registro de negocio (onboarding)
Route::post('/negocios', [NegocioController::class, 'store']);
Route::get('/negocios/{businessRegistrationId}', [NegocioController::class, 'show']);
Route::get('/tipos-negocio', [NegocioController::class, 'getTiposNegocio']);
Route::get('/categorias/{tipoNegocioId}', [NegocioController::class, 'getCategorias']);
Route::put('/negocios/{negocio}', [NegocioController::class, 'update']);
Route::post('/negocios/{negocio}/qr-pago-digital', [NegocioController::class, 'subirQrPagoDigital']);
Route::delete('/negocios/{negocio}/qr-pago-digital', [NegocioController::class, 'eliminarQrPagoDigital']);
Route::post('/negocios/{negocio}/sucursales', [SucursalController::class, 'store']);
Route::get('/negocios/{businessRegistrationId}/approval-status', [NegocioController::class, 'checkApprovalStatus']);

Route::post('/establecimientos', [EstablecimientoController::class, 'store']);
Route::put('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'update']);
Route::get('/establecimientos', [EstablecimientoController::class, 'index']);
Route::get('/establecimientos/{businessRegistrationId}', [EstablecimientoController::class, 'show']);
Route::delete('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'destroy']);

Route::get('/revisarDatos', [RevisarDatosController::class, 'obtenerDatosRevision']);
Route::get('/revisarDatos/{negocioId}', [RevisarDatosController::class, 'obtenerDatosRevision']);

Route::post('/datos-bancarios', [DatosBancariosController::class, 'store']);
Route::get('/datos-bancarios/{businessRegistrationId}', [DatosBancariosController::class, 'show']);
Route::get('/establecimiento/{id}/direccion', [DatosBancariosController::class, 'getEstablecimientoDireccion']);
Route::put('/datos-bancarios/{id}', [DatosBancariosController::class, 'update']);

Route::post('/datos-clave-negocio', [DatosClaveNegocioController::class, 'guardar']);
Route::get('/datos-clave-negocio/{businessRegistrationId}', [DatosClaveNegocioController::class, 'show']);
Route::put('/datos-clave-negocio/{id}', [DatosClaveNegocioController::class, 'update']);

Route::post('/socios/cuenta-bancaria', [SociosCuentaBancariaController::class, 'store']);

// Menú y adicionales (gestión por el negocio)
Route::get('get/menu/adicionales/{id}', [MenuController::class, 'getAdicionales']);
Route::get('menu/{menu_id}/adicionales', [MenuController::class, 'getMenuAdicionales']);
Route::post('menu/{menu_id}/adicionales', [MenuController::class, 'createMenuAdicional']);
Route::put('menu/{menu_id}/adicionales/{adicional_id}', [MenuController::class, 'updateMenuAdicional']);
Route::delete('menu/{menu_id}/adicionales/{adicional_id}', [MenuController::class, 'deleteMenuAdicional']);

// Marcar productos y opciones como agotados (con fecha límite) / disponibles, en lote
Route::post('menu/agotar-lote', [MenuController::class, 'marcarAgotados']);
Route::post('menu/disponible-lote', [MenuController::class, 'marcarDisponibles']);
Route::post('adicionales/agotar-lote', [AdicionalController::class, 'marcarAgotados']);
Route::post('adicionales/disponible-lote', [AdicionalController::class, 'marcarDisponibles']);

// Promociones y banners (gestión por el negocio — el GET público de lectura queda en api_cliente.php)
Route::post('/promociones', [App\Http\Controllers\PromocionController::class, 'store']);
Route::put('/promociones/{id}', [App\Http\Controllers\PromocionController::class, 'update']);
Route::delete('/promociones/{id}', [App\Http\Controllers\PromocionController::class, 'destroy']);
Route::post('/banners', [App\Http\Controllers\BannerController::class, 'store']);
Route::put('/banners/{id}', [App\Http\Controllers\BannerController::class, 'update']);
Route::delete('/banners/{id}', [App\Http\Controllers\BannerController::class, 'destroy']);

// CRUD medios de pago (gestión por el negocio)
Route::post('medios/pago', [App\Http\Controllers\MedioPagoController::class, 'store']);
Route::get('medios/pago/{id}', [App\Http\Controllers\MedioPagoController::class, 'show']);
Route::put('medios/pago/{id}', [App\Http\Controllers\MedioPagoController::class, 'update']);
Route::delete('medios/pago/{id}', [App\Http\Controllers\MedioPagoController::class, 'destroy']);

// rutas app socios (operación diaria)
Route::post('socio/login', [SocioController::class, 'login']);
Route::get('socio/get/pedidos/{id}', [SocioController::class, 'getPedidos']);
Route::get('socio/get/pedido/{id}', [SocioController::class, 'getPedido']);
Route::put('socio/update/estado/pedido/{id}', [PedidoController::class, 'updateEstadoPedido']);
Route::post('socio/pedidos/{id}/solicitar-cancelacion', [PedidoCancelacionController::class, 'requestCancellation']);

Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
Route::post('/categories', [CategoriaController::class, 'store']);
Route::put('/categories/{id}', [CategoriaController::class, 'update']);
Route::put('/categories/{id}/status', [CategoriaController::class, 'toggleStatus']);
Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);
Route::post('/socio/estado', [SocioController::class, 'actualizarEstado']);
Route::post('/socio/sendCode', [SocioController::class, 'sendCode']);
Route::post('/socio/update-password', [SocioController::class, 'updatePassword']);
Route::post('socio/update-token', [SocioController::class, 'updateToken']);
Route::get('/get/documentos/{id}', [SocioController::class, 'getDocumentos']);
// Puente temporal: emite un token para sesiones antiguas guardadas antes del fix
// que empezó a persistir el token de login (no requiere auth:sanctum a propósito).
Route::post('socio/renovar-token', [SocioController::class, 'renovarToken']);

Route::post('/crear/menus', [MenuController::class, 'store']);
Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
Route::get('/getRestaurante/{idLocal}', [PedidoController::class, 'getRestaurantInfo']);
Route::get('/heatmap/{idLocal}', [RatingController::class, 'heatmapData']);
Route::get('/reviews/{idLocal}', [RatingController::class, 'getReviewData']);
Route::get('/rating-evolution', [RatingController::class, 'getRatingEvolution']);

// Ticket PDF del pedido (lo genera/descarga el negocio)
Route::get('pedido/{id}/ticket', [TicketController::class, 'generateTicket']);

// Grupos Adicionales (gestión por el negocio)
Route::get('grupos-adicionales/{empresa_id}', [GrupoAdicionalController::class, 'index']);
Route::post('grupos-adicionales', [GrupoAdicionalController::class, 'store']);
Route::get('grupos-adicionales/detalle/{id}', [GrupoAdicionalController::class, 'show']);
Route::put('grupos-adicionales/{id}', [GrupoAdicionalController::class, 'update']);
Route::delete('grupos-adicionales/{id}', [GrupoAdicionalController::class, 'destroy']);
Route::post('grupos-adicionales/reordenar', [GrupoAdicionalController::class, 'reordenar']);

// Items dentro de un grupo
Route::post('grupos-adicionales/{grupo_id}/items', [GrupoAdicionalController::class, 'addItem']);
Route::put('grupos-adicionales/{grupo_id}/items/{adicional_id}', [GrupoAdicionalController::class, 'updateItem']);
Route::delete('grupos-adicionales/{grupo_id}/items/{adicional_id}', [GrupoAdicionalController::class, 'removeItem']);
Route::get('grupos-adicionales/{grupo_id}/disponibles', [GrupoAdicionalController::class, 'getAvailableItems']);

// Asignar grupos a un menú/producto
Route::post('menu/{menu_id}/grupos', [GrupoAdicionalController::class, 'assignToMenu']);
Route::get('menu/{menu_id}/grupos', [GrupoAdicionalController::class, 'getMenuGroups']);

Route::post('/agendar-entrega', [App\Http\Controllers\EntregaCalendarioController::class, 'agendarEntrega']);

// adicionales (CRUD plano, duplica /adicionales/web — se deja igual que en el original)
Route::get('/adicionales', [AdicionalController::class, 'index']);
Route::post('/adicionales', [AdicionalController::class, 'store']);
Route::get('/adicionales/{adicional}', [AdicionalController::class, 'show']);
Route::put('/adicionales/{adicional}', [AdicionalController::class, 'update']);
Route::delete('/adicionales/{adicional}', [AdicionalController::class, 'destroy']);

// Panel web del negocio (auth:sanctum) — logo, horarios, categorías, menús, adicionales
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/negocio/logo', [PerfilNegocioController::class, 'actualizarLogo']);
    Route::get('/negocio/logo', [PerfilNegocioController::class, 'obtenerLogo']);
    Route::post('/negocio/foto-perfil', [PerfilNegocioController::class, 'actualizarFotoPerfil']);
    Route::get('/negocio/datos', [PerfilNegocioController::class, 'obtenerDatosNegocio']);

    Route::post('/negocio/banner', [PerfilNegocioController::class, 'actualizarBanner']);
    Route::get('/establecimiento/actual', [PerfilNegocioController::class, 'obtenerEstablecimientoActual']);
    Route::put('/establecimiento/actualizar', [PerfilNegocioController::class, 'actualizarEstablecimiento']);
    Route::get('/negocio/pos-settings', [PerfilNegocioController::class, 'obtenerConfiguracionPOS']);
    Route::put('/negocio/pos-settings', [PerfilNegocioController::class, 'actualizarConfiguracionPOS']);

    Route::get('/negocio/pago-digital', [PerfilNegocioController::class, 'obtenerConfiguracionPagoDigital']);
    Route::put('/negocio/pago-digital', [PerfilNegocioController::class, 'actualizarConfiguracionPagoDigital']);
    Route::post('/negocio/pago-digital/qr', [PerfilNegocioController::class, 'actualizarQrPagoDigital']);
    Route::delete('/negocio/pago-digital/qr', [PerfilNegocioController::class, 'eliminarQrPagoDigital']);

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);
    Route::post('/categoria/web/reordenar', [CategoriaController::class, 'reordenar']);

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);
    Route::post('/menus/web/reordenar', [MenuController::class, 'reordenarMenus']);
    Route::get('/menus/categoria/{categoria_id}', [MenuController::class, 'getMenusByCategory']);

    Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
    Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
    Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
    Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
    Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);
});

// categorias (duplica el bloque de arriba — se deja igual que en el original)
Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
Route::post('/categories', [CategoriaController::class, 'store']);
Route::put('/categories/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);

// Crear menus (duplica el bloque de arriba — se deja igual que en el original)
Route::post('/crear/menus', [MenuController::class, 'store']);
Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
Route::put('/menus/{id}', [MenuController::class, 'update']);
Route::delete('/menus/{id}', [MenuController::class, 'destroy']);

// rutas para la web (duplica casi exacto el bloque de panel web de arriba — se deja igual que en el original)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/negocio/logo', [PerfilNegocioController::class, 'actualizarLogo']);
    Route::post('/negocio/horarios', [PerfilNegocioController::class, 'guardarHorario']);
    Route::put('/negocio/horarios/{id}', [PerfilNegocioController::class, 'guardarHorario']);
    Route::delete('/negocio/horarios/{id}', [PerfilNegocioController::class, 'eliminarHorario']);
    Route::get('/negocio/logo', [PerfilNegocioController::class, 'obtenerLogo']);
    Route::post('/negocio/foto-perfil', [PerfilNegocioController::class, 'actualizarFotoPerfil']);
    Route::get('/negocio/datos', [PerfilNegocioController::class, 'obtenerDatosNegocio']);

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);
    Route::post('/categoria/web/reordenar', [CategoriaController::class, 'reordenar']);

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);
    Route::post('/menus/web/reordenar', [MenuController::class, 'reordenarMenus']);

    Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
    Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
    Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
    Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
    Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);
    Route::post('/account/request-deletion', [AccountDeletionController::class, 'requestDeletion']);
});
