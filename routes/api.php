<?php

use App\Http\Controllers\AdicionalController;
use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\BikerController;
use App\Http\Controllers\CategoriaAdicionalController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DatosBancariosRepartoController;
use App\Http\Controllers\EntregaCalendarioController;
use App\Http\Controllers\MotorizadoController;
use App\Http\Controllers\PerfilNegocioController;
use App\Http\Controllers\RegistrationStatusController;
use App\Http\Controllers\RegistroVehiculoController;
use App\Http\Controllers\RepartoRegistroController;
use App\Http\Controllers\SociosCuentaBancariaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\SocioController;
use Illuminate\Http\Request;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\DatosClaveNegocioController;
use App\Http\Controllers\DatosBancariosController;
use App\Http\Controllers\RevisarDatosController;
use App\Http\Controllers\IdsController;
use App\Http\Middleware\EncryptionHandler;

use App\Http\Controllers\DatosPersonalesRepartoController;
use App\Http\Controllers\LocalesController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoTrackingController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\TipoNegocioController;
use Illuminate\Support\Facades\Route;


Route::post('/admin/login', [AuthAdminController::class, 'login']);

Route::post('/admin/verify-email', [AuthAdminController::class, 'verifyEmail']);
Route::post('/admin/verify-code', [AuthAdminController::class, 'verifyCode']);
Route::post('/admin/reset-password', [AuthAdminController::class, 'resetPassword']);
Route::middleware('auth:sanctum')->get('/admin/check-auth', [AuthAdminController::class, 'checkAuth']);



Route::middleware('auth:sanctum')->group(function () {
    // Rutas para administradores
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Gestión de usuarios
        Route::controller(UserController::class)->group(function () {
            Route::get('/user', 'all');
            Route::post('/users/change/state/{id}', 'changeState');
            Route::post('/users/create', 'store');
            Route::delete('/users/delete/{id}', 'delete');
        });

        // Gestión de socios
        Route::controller(SocioController::class)->group(function () {
            Route::get('/socio', 'all');
            Route::post('/socio/change/state/{id}', 'changeState');
            Route::get('/socio/{id}/details', 'getDetails');
            Route::post('/socio/{id}/aprobar', 'aprobar');
            Route::delete('/socio/{id}/delete', 'delete');
        });

        // Gestión de motorizados
        Route::controller(MotorizadoController::class)->group(function () {
            Route::get('/motorizado', 'all');
            Route::post('/motorizado/change/state/{id}', 'changeState');
            Route::get('/motorizado/{id}/details', 'getDetails');
            Route::post('/motorizado/{id}/aprobar', 'aprobar');
            Route::delete('/motorizado/{id}/delete', 'delete');
        });
    });

    // Rutas para socios (accesibles por usuarios con rol 'negocio')
    Route::middleware('role:negocio')->group(function () {
        Route::controller(SocioController::class)->group(function () {
            Route::get('/socio/pedidos/{id}', 'getPedidos');
            Route::post('/socio/pedidos/update-estado/{id}', 'updateEstadoPedido');
        });
    });
    // Rutas para motorizados
    Route::middleware('role:motorizado')->prefix('motorizado')->group(function () {
        Route::controller(MotorizadoController::class)->group(function () {
            Route::get('/perfil', 'perfil');
            Route::put('/actualizar', 'actualizar');
        });
    });
});



   // Rutas de autenticación y verificación del formulario principal y envio del correo
 

Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/verify', 'verify');
    Route::post('/resend-code', 'resendCode');
    Route::post('/register/{id}/update-email', 'updateEmail');
    Route::get('/register/{id}', 'getRegistration');
});

// Otras rutas
Route::post('/negocios', [NegocioController::class, 'store']);
Route::get('/negocios/{businessRegistrationId}', [NegocioController::class, 'show']);
Route::get('/tipos-negocio', [NegocioController::class, 'getTiposNegocio']);
Route::get('/categorias/{tipoNegocioId}', [NegocioController::class, 'getCategorias']);
Route::post('/negocios', [NegocioController::class, 'store']);
Route::put('/negocios/{negocio}', [NegocioController::class, 'update']);
Route::post('/negocios/{negocio}/sucursales', [SucursalController::class, 'store']);
Route::get('/negocios/{businessRegistrationId}/approval-status', [NegocioController::class, 'checkApprovalStatus']);


// Route::get('/obtener-ultimos-ids', [IdsController::class, 'obtenerUltimosIds']);

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

Route::post('/reparto/registro', [RepartoRegistroController::class, 'store']);
Route::post('/reparto/check-status', [RepartoRegistroController::class, 'checkStatus']);
Route::get('/reparto/{id}/status', [RepartoRegistroController::class, 'getRegistrationStatus']);
Route::post('/reparto/{id}/update-email', [RepartoRegistroController::class, 'updateEmail']);
Route::get('/reparto/{id}', [RepartoRegistroController::class, 'getRegistration']);

// Rutas para datos personales
Route::get('/departamentos', [DatosPersonalesRepartoController::class, 'obtenerDepartamentos']);
Route::get('/provincias/{departamentoId}', [DatosPersonalesRepartoController::class, 'obtenerProvincias']);
Route::get('/distritos/{departamentoId}/{provinciaId}', [DatosPersonalesRepartoController::class, 'obtenerDistritos']);
Route::post('/datos-personales', [DatosPersonalesRepartoController::class, 'guardar']);
Route::get('/datos-personales/{repartoRegistroId}', [DatosPersonalesRepartoController::class, 'show']);
Route::post('/datos-personales/{id}', [DatosPersonalesRepartoController::class, 'update']);


// Rutas para datos bancarios
Route::get('/bancos', [DatosBancariosRepartoController::class, 'obtenerBancos']);
Route::get('/tipos-cuenta', [DatosBancariosRepartoController::class, 'obtenerTiposCuenta']);
Route::post('/cuenta-bancaria', [DatosBancariosRepartoController::class, 'guardarCuentaBancaria']);
Route::get('/cuenta-bancaria/{repartoRegistroId}', [DatosBancariosRepartoController::class, 'show']);
Route::post('/cuenta-bancaria/{id}', [DatosBancariosRepartoController::class, 'update']);


Route::post('/registro-vehiculo', [RegistroVehiculoController::class, 'guardar']);

Route::get('/registro-vehiculo/{repartoRegistroId}', [RegistroVehiculoController::class, 'mostrar']);

Route::post('/socios/cuenta-bancaria', [SociosCuentaBancariaController::class, 'store']);
Route::post('/confirmar-pedido', [PedidoController::class, 'store']);
Route::get('/pedidos/{id}', [PedidoTrackingController::class, 'obtenerEstado']);
Route::get('motorcycle-location/{idPedido}', [LocationController::class, 'fetchMotorcycleLocation']);



//rutas app clientes
Route::post('/send-code', [ClienteController::class, 'sendCode']);
Route::post('/profile', [ClienteController::class, 'store']);
Route::post('/get-dni', [ClienteController::class, 'getDni']);
Route::post('/upload-photos', [ClienteController::class, 'uploadPhotos']);
Route::post('/update-profile', [ClienteController::class, 'actualizarInfoCliente']);
Route::post('/send-code-phone', [ClienteController::class, 'sendCodePhone']);
Route::get('/get/tipo/negocio', [TipoNegocioController::class, 'getAll']);
Route::get('/get/locales/top/{id}', [LocalesController::class, 'getLocalesTop']);
Route::get('/get/locales/{id}/{category?}', [LocalesController::class, 'getLocales']);
Route::get('/busqueda/locales/{id}/{term?}', [LocalesController::class, 'searchLocales']);
Route::get('/listar/menus/categoria/{empresa_id}', [MenuController::class, 'getMenuCategoria']);
Route::get('/customer-local-location/{idPedido}', [PedidoController::class, 'getLocalYcustomerPosition']);
Route::post('/login/cliente', [ClienteController::class, 'login']);
Route::get('pedidos/cliente/{idCliente}', [PedidoController::class, 'getPedidosCliente']);
Route::post('/ratings', [RatingController::class, 'store']); // Guardar calificación
Route::get('/ratings/{id_pedido}', [RatingController::class, 'getRatings']); 
Route::get('/getMotorizado/{idPedido}', [PedidoController::class, 'getMotorizado']);
Route::get('/getMotorizadoInfo/{idPedido}', [PedidoController::class, 'getMotorizadoInfo']);
Route::get('/getPerfil/{idCliente}', [ClienteController::class, 'getPerfil']);
Route::post('/enviar-correo-pedido-entregado', [PedidoController::class, 'enviarCorreoPedidoEntregado']);
Route::get('/get/pedidos/{id}', [PedidoController::class, 'getPedido']);

//rutas app socios
Route::post('socio/login', [SocioController::class, 'login']);
Route::get('socio/get/pedidos/{id}', [SocioController::class, 'getPedidos']);
Route::put('socio/update/estado/pedido/{id}', [PedidoController::class, 'updateEstadoPedido']);
Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
Route::post('/categories', [CategoriaController::class, 'store']);
Route::put('/categories/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);

Route::post('/crear/menus', [MenuController::class, 'store']);
Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
Route::get('/getRestaurante/{idLocal}', [PedidoController::class, 'getRestaurantInfo']);
Route::get('/heatmap/{idLocal}', [RatingController::class, 'heatmapData']);
Route::get('/reviews/{idLocal}', [RatingController::class, 'getReviewData']);
Route::get('/rating-evolution', [RatingController::class, 'getRatingEvolution']);

//rutas app repartidores
Route::post('biker/login', [BikerController::class, 'login']);
Route::get('biker/get/pedidos/{id}', [BikerController::class, 'getPedidos']);
Route::post('biker/iniciar_viaje', [PedidoController::class, 'iniciarViaje']);
Route::post('biker/location/update', [BikerController::class, 'updateLocation']);
Route::post('biker/update-token', [BikerController::class, 'updateToken']);
Route::get('/ratings/biker/{idUsuario}', [RatingController::class, 'getRatingsBiker']); 
Route::get('/biker/perfil/{idUsuario}', [BikerController::class, 'getPerfl']); 
Route::post('/update-estado/pedido', [PedidoTrackingController::class, 'updateEstado']);
Route::post('/biker/alerta-auxilio', [PedidoController::class, 'mandarAlertaDeAuxilio']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/negocio/logo', [PerfilNegocioController::class, 'actualizarLogo']);
    Route::post('/negocio/horarios', [PerfilNegocioController::class, 'guardarHorario']);
    Route::get('/negocio/logo', [PerfilNegocioController::class, 'obtenerLogo']);
    Route::post('/negocio/foto-perfil', [PerfilNegocioController::class, 'actualizarFotoPerfil']);
    Route::get('/negocio/datos', [PerfilNegocioController::class, 'obtenerDatosNegocio']);

    // categorias

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);

    //Crear menus

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);
    Route::get('/menus/categoria/{categoria_id}', [MenuController::class, 'getMenusByCategory']);

       // Rutas para adicionales
       Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
       Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
       Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
       Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
       Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);
   
       // Rutas para categorías de adicionales
       Route::get('/categorias-adicionales/web/{empresa_id}', [CategoriaAdicionalController::class, 'obtenerCategoriasAdicionales']);
       Route::post('/categorias-adicionales/web', [CategoriaAdicionalController::class, 'crearCategoriaAdicional']);
       Route::get('/categorias-adicionales/web/detalle/{categoriaAdicional}', [CategoriaAdicionalController::class, 'obtenerCategoriaAdicional']);
       Route::put('/categorias-adicionales/web/{categoriaAdicional}', [CategoriaAdicionalController::class, 'actualizarCategoriaAdicional']);
       Route::delete('/categorias-adicionales/web/{categoriaAdicional}', [CategoriaAdicionalController::class, 'eliminarCategoriaAdicional']);
});



Route::post('/agendar-entrega', [EntregaCalendarioController::class, 'agendarEntrega']);

// adicionales
Route::get('/adicionales', [AdicionalController::class, 'index']);
Route::post('/adicionales', [AdicionalController::class, 'store']);
Route::get('/adicionales/{adicional}', [AdicionalController::class, 'show']);
Route::put('/adicionales/{adicional}', [AdicionalController::class, 'update']);
Route::delete('/adicionales/{adicional}', [AdicionalController::class, 'destroy']);

// categoria adicionales
Route::get('/categorias-adicionales', [CategoriaAdicionalController::class, 'index']);
Route::post('/categorias-adicionales', [CategoriaAdicionalController::class, 'store']);
Route::get('/categorias-adicionales/{categoriaAdicional}', [CategoriaAdicionalController::class, 'show']);
Route::put('/categorias-adicionales/{categoriaAdicional}', [CategoriaAdicionalController::class, 'update']);
Route::delete('/categorias-adicionales/{categoriaAdicional}', [CategoriaAdicionalController::class, 'destroy']);

  // categorias

  Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
  Route::post('/categories', [CategoriaController::class, 'store']);
  Route::put('/categories/{id}', [CategoriaController::class, 'update']);
  Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);

  //Crear menus

  Route::post('/crear/menus', [MenuController::class, 'store']);
  Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
  Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
  Route::put('/menus/{id}', [MenuController::class, 'update']);
  Route::delete('/menus/{id}', [MenuController::class, 'destroy']);


// rutas para la web

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/negocio/logo', [PerfilNegocioController::class, 'actualizarLogo']);
    Route::post('/negocio/horarios', [PerfilNegocioController::class, 'guardarHorario']);
    Route::get('/negocio/logo', [PerfilNegocioController::class, 'obtenerLogo']);
    Route::post('/negocio/foto-perfil', [PerfilNegocioController::class, 'actualizarFotoPerfil']);
    Route::get('/negocio/datos', [PerfilNegocioController::class, 'obtenerDatosNegocio']);

    // categorias

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);

    //Crear menus

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);

       // Rutas para adicionales
       Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
       Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
       Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
       Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
       Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);
   
       // Rutas para categorías de adicionales
       Route::get('/categorias-adicionales/web/{empresa_id}', [CategoriaAdicionalController::class, 'obtenerCategoriasAdicionales']);
       Route::post('/categorias-adicionales/web', [CategoriaAdicionalController::class, 'crearCategoriaAdicional']);
       Route::get('/categorias-adicionales/web/detalle/{categoriaAdicional}', [CategoriaAdicionalController::class, 'obtenerCategoriaAdicional']);
       Route::put('/categorias-adicionales/web/{categoriaAdicional}', [CategoriaAdicionalController::class, 'actualizarCategoriaAdicional']);
       Route::delete('/categorias-adicionales/web/{categoriaAdicional}', [CategoriaAdicionalController::class, 'eliminarCategoriaAdicional']);
});

Route::post('/register/check-status', [RegistrationStatusController::class, 'checkStatus']);
Route::get('/register/{id}/status', [RegistrationStatusController::class, 'getRegistrationStatus']);
// En routes/api.php
Route::post('/register/{id}/reset', [RegistrationStatusController::class, 'resetRegistration']);