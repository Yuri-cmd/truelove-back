<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\ClienteDeletionAdminController;
use App\Http\Controllers\AdicionalController;
use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BikerController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CuotaMotorizadoController;
use App\Http\Controllers\CuotaSocioController;
use App\Http\Controllers\DatosBancariosController;
use App\Http\Controllers\DatosBancariosRepartoController;
use App\Http\Controllers\DatosClaveNegocioController;
use App\Http\Controllers\DatosPersonalesRepartoController;
use App\Http\Controllers\DescuentoClienteController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EntregaCalendarioController;
use App\Http\Controllers\EstablecimientoController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\IdsController;
use App\Http\Controllers\KilometrosTarifaController;
use App\Http\Controllers\TarifaRangoController;
use App\Http\Controllers\LocalesController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MedioPagoController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MotorizadoController;
use App\Http\Controllers\NegocioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PedidoTrackingController;
use App\Http\Controllers\PerfilNegocioController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\RegistrationStatusController;
use App\Http\Controllers\RegistroVehiculoController;
use App\Http\Controllers\RepartoRegistroCompletoController;
use App\Http\Controllers\RepartoRegistroController;
use App\Http\Controllers\RevisarDatosController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\SociosCuentaBancariaController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\TipoNegocioController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InfoClienteController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\GrupoAdicionalController;
use App\Http\Middleware\EncryptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthAdminController::class, 'login']);

Route::post('/admin/verify-email', [AuthAdminController::class, 'verifyEmail']);
Route::post('/admin/verify-code', [AuthAdminController::class, 'verifyCode']);
Route::post('/admin/reset-password', [AuthAdminController::class, 'resetPassword']);

Route::post('/notifications/update-status', [App\Http\Controllers\NotificationTrackingController::class, 'updateStatus']);

Route::get('/app-version/{app_name}', [AppVersionController::class, 'getVersion']);

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
        Route::get('/deletion-requests', [AccountDeletionController::class, 'getPendingRequests']);
        Route::post('/deletion-requests/{id}/approve', [AccountDeletionController::class, 'approveRequest']);
        Route::post('/deletion-requests/{id}/reject', [AccountDeletionController::class, 'rejectRequest']);
        Route::get('/deletion-requests/history', [AccountDeletionController::class, 'getRequestHistory']);

        // Rutas para solicitudes de eliminación de cuenta de clientes
        Route::get('/cliente-deletion-requests', [ClienteDeletionAdminController::class, 'getPendingRequests']);
        Route::get('/cliente-deletion-requests/history', [ClienteDeletionAdminController::class, 'getRequestHistory']);
        Route::get('/cliente-deletion-requests/stats', [ClienteDeletionAdminController::class, 'getStats']);
        Route::post('/cliente-deletion-requests/{id}/approve', [ClienteDeletionAdminController::class, 'approveRequest']);
        Route::post('/cliente-deletion-requests/{id}/reject', [ClienteDeletionAdminController::class, 'rejectRequest']);

        // Gestión de socios
        Route::controller(SocioController::class)->group(function () {
            Route::get('/socio', 'all');
            Route::post('/socio/change/state/{id}', 'changeState');
            Route::get('/socio/{id}/details', 'getDetails');
            Route::post('/socio/{id}/aprobar', 'aprobar');
            Route::delete('/socio/{id}/delete', 'delete');
            // Route::get('/locales/empresas', 'getLocalesEmpresas');
            // Route::post('/locales/puntuacion/{id}', 'actualizarPuntuacionLocal');
            Route::get('/locales/prioridad', 'getLocalesPorPrioridad');
            Route::post('/locales/prioridad/{id}', 'actualizarPrioridadLocal');
            Route::get('/locales/prioridad/estadisticas', 'getEstadisticasPrioridad');
            Route::get('/locales/all', 'getAllLocales');
        });

        // Gestión de motorizados
        Route::controller(MotorizadoController::class)->group(function () {
            Route::get('/motorizado', 'all');
            Route::post('/motorizado/change/state/{id}', 'changeState');
            Route::get('/motorizado/{id}/details', 'getDetails');
            Route::put('/motorizado/{id}/update', 'update');
            Route::put('/motorizado/{id}/update-documentos', 'updateDocumentos');
            Route::post('/motorizado/{id}/aprobar', 'aprobar');
            Route::delete('/motorizado/{id}/delete', 'delete');
            Route::post('/motorizado/{id}/actualizar-pedidos', 'actualizarCantidadPedidos');
            Route::get('/motorizado/{id}/entrega-calendario', 'getEntregaCalendarios');
        });

        // Gestión de clientes
        Route::controller(InfoClienteController::class)->group(function () {
            Route::get('/cliente', 'all');
            Route::get('/cliente/{id}/details', 'getDetails');
            Route::delete('/cliente/{id}/delete', 'delete');
        });

        Route::controller(HorarioController::class)->group(function () {
            Route::get('/horarios', 'getAllHorarios');
            Route::get('/horarios/grupales', 'getHorariosGrupales');
            Route::get('/horarios/individuales', 'getHorariosIndividuales');
            Route::get('/horarios/{id}', 'getHorario');
            Route::get('/horarios/motorizado/{motorizadoId}', 'getHorarioMotorizado');
            Route::post('/horarios', 'createHorario');
            Route::put('/horarios/{id}', 'updateHorario');
            Route::delete('/horarios/{id}', 'deleteHorario');
            Route::get('/horarios/motorizados/disponibles', 'getMotorizadosDisponibles');
            Route::get('/horarios/motorizados/todos', 'getTodosMotorizados');
        });
        Route::put('/entrega-calendario/{id}/estado', [EntregaCalendarioController::class, 'actualizarEstado']);
        Route::controller(CuotaMotorizadoController::class)->prefix('cuotas-motorizados')->group(function () {
            Route::get('/', 'index');
            Route::post('/generar-semanal', 'generarCuotasSemanal');
            Route::post('/{id}/marcar-pagada', 'marcarPagada');
            Route::post('/{id}/revertir-pago', 'revertirPago');
            Route::get('/estadisticas', 'estadisticas');
            Route::get('/motorizados-aprobados', 'getMotorizadosAprobados');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });

        // Rutas para Kilómetros Tarifa (sistema antiguo, mantener por compatibilidad)
        Route::controller(KilometrosTarifaController::class)->prefix('kilometros-tarifa')->group(function () {
            Route::get('/', 'index');
            Route::get('/activa', 'getActiva');
            Route::get('/{id}', 'show');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::post('/{id}/activar', 'activar');
        });

        // Rutas para Tarifas por Rangos (sistema nuevo)
        Route::controller(TarifaRangoController::class)->prefix('tarifas-rangos')->group(function () {
            // Endpoints específicos PRIMERO (antes de las rutas con {id})
            Route::get('/activa', 'getActiva');
            Route::get('/locales', 'getLocales');
            Route::get('/clientes', 'getClientes');
            Route::post('/calcular-preview', 'calcularPreview');
            // Rutas para config por local
            Route::get('/locales-con-config', 'getLocalesConConfig');
            Route::get('/config-local/{idLocal}', 'getConfigLocal');
            Route::post('/config-local/{idLocal}', 'saveConfigLocal');
            Route::delete('/config-local/{idLocal}', 'deleteConfigLocal');

            // Rutas CRUD (con {id} al final)
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::post('/{id}/activar', 'activar');
        });

        // Rutas para administración de Tipos de Negocio y Categorías
        Route::controller(TipoNegocioController::class)->prefix('tipos-negocio')->group(function () {
            Route::get('/admin/all', 'getAllAdmin');
            Route::post('/admin', 'storeAdmin');
            Route::put('/admin/{id}', 'updateAdmin');
            Route::delete('/admin/{id}', 'destroyAdmin');
            Route::post('/admin/{id}/toggle-status', 'toggleStatus');
        });

        Route::prefix('categorias')->group(function () {
            Route::get('/admin/tipo/{tipoNegocioId}', [TipoNegocioController::class, 'getCategoriasAdmin']);
            Route::post('/admin', [TipoNegocioController::class, 'storeCategoria']);
            Route::put('/admin/{id}', [TipoNegocioController::class, 'updateCategoria']);
            Route::delete('/admin/{id}', [TipoNegocioController::class, 'destroyCategoria']);
            Route::post('/admin/{id}/toggle-status', [TipoNegocioController::class, 'toggleCategoriaStatus']);
        });

        Route::controller(CuotaSocioController::class)->prefix('cuotas-socios')->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
            Route::get('/activa', 'getActiva');
            Route::get('/estadisticas', 'estadisticas');

            // Gestión de pagos/comprobantes
            Route::get('/pagos', 'getPagos');
            Route::put('/pagos/{id}/aprobar', 'aprobarPago');
            Route::put('/pagos/{id}/rechazar', 'rechazarPago');

            // Gestión de asignación de cuotas y períodos
            Route::post('/asignar-cuota', 'asignarCuotaASocio');
            Route::delete('/remover-cuota/{socioId}', 'removerCuotaDeSocio');
            Route::get('/periodos/{socioId}', 'verPeriodosDeSocio');
            Route::get('/estado-pagos/{socioId}', 'getEstadoPagosSocio');

            // Gestión de día de pago
            Route::put('/{id}/actualizar-dia-pago', 'actualizarDiaPago');

            // Cálculo de comisiones por porcentaje
            Route::post('/calcular-periodo/{periodoId}', 'calcularPeriodo');
            Route::post('/calcular-socio/{socioId}', 'calcularCuotasSocio');
            Route::post('/calcular-todos/{cuotaId}', 'calcularTodasLasCuotas');
            Route::get('/detalle-periodo/{periodoId}', 'detallePeriodo');

            // IMPORTANTE: Esta ruta debe ir al final para evitar conflictos
            Route::get('/{id}', 'show');
        });
    });

    // Rutas para socios (accesibles por usuarios con rol 'negocio')
    Route::middleware('role:negocio')->group(function () {
        Route::get('/socio/perfil', [SocioController::class, 'getAuthenticatedSocioDetails']);
        Route::put('/socio/personal-info', [SocioController::class, 'actualizarInformaciónPersonal']);
        Route::put('/socio/business-info', [SocioController::class, 'actualizarInformaciónNegocio']);
        Route::put('/socio/establishment', [SocioController::class, 'actualizarEstablecimiento']);
        Route::put('/socio/business-key-info', [SocioController::class, 'actualizarDatosClaveNegocio']);
        Route::put('/socio/bank-data', [SocioController::class, 'actualizarDatosBancarios']);
        Route::post('/socio/bank-account', [SocioController::class, 'actualizarCuentaBancaria']);
        Route::put('/socio/change-password', [SocioController::class, 'changePassword']);

        Route::controller(SocioController::class)->group(function () {
            Route::get('/socio/pedidos/{id}', 'getPedidos');
            Route::post('/socio/pedidos/update-estado/{id}', 'updateEstadoPedido');
            Route::post('/socio/update-token-web', 'updateTokenWeb');
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

// Rutas de autenticación y verificación del formulario principal y envio del correo

Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/verify', 'verify');
    Route::post('/resend-code', 'resendCode');
    Route::post('/register/{id}/update-email', 'updateEmail');
    Route::get('/register/{id}', 'getRegistration');
});

Route::post('/prueba-notificacion', [PedidoController::class, 'pruebaNoticacion']);

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
Route::put('/repartidores/info/{id}', [BikerController::class, 'updateInfo']);

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
Route::post('/cancelar-pedido', [PedidoController::class, 'cancelarPedido']);
Route::get('motorcycle-location/{idPedido}', [LocationController::class, 'fetchMotorcycleLocation']);

Route::get('prueba', [PedidoController::class, 'prueba']);
Route::get('local-esta-abierto/{idLocal}', [NegocioController::class, 'localEstaAbierto']);
// rutas app clientes
Route::post('/send-code', [ClienteController::class, 'sendCode']);
Route::post('/profile', [ClienteController::class, 'store']);
Route::post('/get-dni', [ClienteController::class, 'getDni']);
Route::post('/upload-photos', [ClienteController::class, 'uploadPhotos']);
Route::post('/update-profile', [ClienteController::class, 'actualizarInfoCliente']);
Route::post('/send-code-phone', [ClienteController::class, 'sendCodePhone']);
Route::get('/get/tipo/negocio', [TipoNegocioController::class, 'getAll']);
Route::get('/get/locales/top/{id?}', [LocalesController::class, 'getLocalesTop']);
Route::get('/get/locales/{id}/{category?}', [LocalesController::class, 'getLocales']);
Route::get('/busqueda/locales/{id}/{term?}', [LocalesController::class, 'searchLocales']);
Route::get('/listar/menus/categoria/{empresa_id}', [MenuController::class, 'getMenuCategoria']);
Route::get('/customer-local-location/{idPedido}', [PedidoController::class, 'getLocalYcustomerPosition']);
Route::post('/login/cliente', [ClienteController::class, 'login']);
Route::get('pedidos/cliente/{idCliente}', [PedidoController::class, 'getPedidosCliente']);
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
Route::get('get/menu/adicionales/{id}', [MenuController::class, 'getAdicionales']);

// Rutas para adicionales por menú (producto) - CRUD directo
Route::get('menu/{menu_id}/adicionales', [MenuController::class, 'getMenuAdicionales']);
Route::post('menu/{menu_id}/adicionales', [MenuController::class, 'createMenuAdicional']);
Route::put('menu/{menu_id}/adicionales/{adicional_id}', [MenuController::class, 'updateMenuAdicional']);
Route::delete('menu/{menu_id}/adicionales/{adicional_id}', [MenuController::class, 'deleteMenuAdicional']);
Route::get('get/medios/pago/{idEmpresa?}', [MedioPagoController::class, 'index']);
Route::get('/promociones', [PromocionController::class, 'index']);
Route::post('/promociones', [PromocionController::class, 'store']);
Route::get('/promociones/{id}', [PromocionController::class, 'show']);
Route::put('/promociones/{id}', [PromocionController::class, 'update']);
Route::delete('/promociones/{id}', [PromocionController::class, 'destroy']);
Route::get('/banners', [BannerController::class, 'index']);
Route::post('/cliente/sendCode', [ClienteController::class, 'sendCodeNew']);
Route::post('/cliente/update-password', [ClienteController::class, 'updatePassword']);
Route::get('/cliente/repetir/orden/{idPedido}', [PedidoController::class, 'repetirOrden']);
Route::get('/pedidos/{id}/verificar-confirmacion', [PedidoController::class, 'verificarConfirmacion']);
Route::put('socio/update/verificar/confirmacion/{idPedido}', [PedidoController::class, 'updateVerificarConfirmacion']);
Route::get('get/precio/delivery/{idLocal}/{idCliente}', [PedidoController::class, 'calcularPrecioDelivery']);
Route::get('validar/cupon/descuento/{code}/{idCliente}', [DescuentoClienteController::class, 'validarCodeDescuento']);
Route::post('cliente/update-token', [ClienteController::class, 'updateToken']);
Route::get('socio/entrega/documento/{idLocal}', [SocioController::class, 'socioEntregaDocumento']);
Route::post('upload-payment-proof', [PedidoController::class, 'uploadPaymentProof']);

// Ticket PDF
Route::get('pedido/{id}/ticket', [TicketController::class, 'generateTicket']);

// Grupos Adicionalesss
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


// Rutas para eliminación de cuenta de cliente
Route::post('cliente/buscar-documento', [ClienteController::class, 'buscarPorDocumento']);
Route::post('cliente/solicitar-eliminacion', [ClienteController::class, 'solicitarEliminacionCuenta']);
Route::post('cliente/verificar-codigo-eliminacion', [ClienteController::class, 'verificarCodigoEliminacion']);

// chat
Route::get('/chats/{pedidoId}', [ChatController::class, 'index']);
Route::post('/chats/storeCliente', [ChatController::class, 'storeCliente']);
Route::post('/chats/storeMotorizado', [ChatController::class, 'storeMotorizado']);

// CRUD PAGOS
Route::post('medios/pago', [MedioPagoController::class, 'store']);
Route::get('medios/pago/{id}', [MedioPagoController::class, 'show']);
Route::put('medios/pago/{id}', [MedioPagoController::class, 'update']);
Route::delete('medios/pago/{id}', [MedioPagoController::class, 'destroy']);

// CRUD banner
Route::get('/banners/{id}', [BannerController::class, 'show']);
Route::post('/banners', [BannerController::class, 'store']);
Route::put('/banners/{id}', [BannerController::class, 'update']);
Route::delete('/banners/{id}', [BannerController::class, 'destroy']);
// rutas app socios
Route::post('socio/login', [SocioController::class, 'login']);
Route::get('socio/get/pedidos/{id}', [SocioController::class, 'getPedidos']);
Route::get('socio/get/pedido/{id}', [SocioController::class, 'getPedido']);
Route::put('socio/update/estado/pedido/{id}', [PedidoController::class, 'updateEstadoPedido']);
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

Route::post('/crear/menus', [MenuController::class, 'store']);
Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
Route::get('/getRestaurante/{idLocal}', [PedidoController::class, 'getRestaurantInfo']);
Route::get('/heatmap/{idLocal}', [RatingController::class, 'heatmapData']);
Route::get('/reviews/{idLocal}', [RatingController::class, 'getReviewData']);
Route::get('/rating-evolution', [RatingController::class, 'getRatingEvolution']);

// rutas app repartidores
Route::post('biker/login', [BikerController::class, 'login']);
Route::get('biker/condiciones/{id}', [BikerController::class, 'condiciones']);
Route::get('biker/get/pedidos/{id}', [BikerController::class, 'getPedidos']);
Route::post('biker/iniciar_viaje', [PedidoController::class, 'iniciarViaje']);
Route::post('biker/location/update', [BikerController::class, 'updateLocation']);
Route::post('biker/update-token', [BikerController::class, 'updateToken']);
Route::get('/ratings/biker/{idUsuario}', [RatingController::class, 'getRatingsBiker']);
Route::get('/biker/perfil/{idUsuario}', [BikerController::class, 'getPerfl']);
Route::post('/update-estado/pedido', [PedidoTrackingController::class, 'updateEstado']);
Route::post('/biker/alerta-auxilio', [PedidoController::class, 'mandarAlertaDeAuxilio']);
Route::post('/repartidor/estado', [BikerController::class, 'actualizarEstado']);
Route::post('/biker/sendCode', [BikerController::class, 'sendCode']);
Route::post('/biker/update-password', [BikerController::class, 'updatePassword']);
Route::get('/biker/viaje-activo/{idBiker}', [BikerController::class, 'viajeActivo']);
Route::get('/biker/viajes-activos/{idBiker}', [BikerController::class, 'viajesActivos']);
Route::get('/biker/viajes/{idBiker}', [BikerController::class, 'viajesListado']);

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
    // categorias

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);
    Route::post('/categoria/web/reordenar', [CategoriaController::class, 'reordenar']);

    // Crear menus

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);
    Route::post('/menus/web/reordenar', [MenuController::class, 'reordenarMenus']);
    Route::get('/menus/categoria/{categoria_id}', [MenuController::class, 'getMenusByCategory']);

    // Rutas para adicionales
    Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
    Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
    Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
    Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
    Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);

});

// En tu archivo de rutas
Route::get('/rankings/clients', [RatingController::class, 'getTopClients']);
Route::get('/rankings/stores', [RatingController::class, 'getTopStores']);

Route::get('/locales/ranking', [PedidoController::class, 'getLocalesPorPedidos']);
Route::post('/agendar-entrega', [EntregaCalendarioController::class, 'agendarEntrega']);

// adicionales
Route::get('/adicionales', [AdicionalController::class, 'index']);
Route::post('/adicionales', [AdicionalController::class, 'store']);
Route::get('/adicionales/{adicional}', [AdicionalController::class, 'show']);
Route::put('/adicionales/{adicional}', [AdicionalController::class, 'update']);
Route::delete('/adicionales/{adicional}', [AdicionalController::class, 'destroy']);

// categorias

Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
Route::post('/categories', [CategoriaController::class, 'store']);
Route::put('/categories/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);

// Crear menus

Route::post('/crear/menus', [MenuController::class, 'store']);
Route::get('/listar/menus/{empresa_id}', [MenuController::class, 'index']);
Route::put('/menu/{id}/status', [MenuController::class, 'updateStatus']);
Route::put('/menus/{id}', [MenuController::class, 'update']);
Route::delete('/menus/{id}', [MenuController::class, 'destroy']);

// rutas para la web

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/negocio/logo', [PerfilNegocioController::class, 'actualizarLogo']);
    Route::post('/negocio/horarios', [PerfilNegocioController::class, 'guardarHorario']);
    Route::put('/negocio/horarios/{id}', [PerfilNegocioController::class, 'guardarHorario']);
    Route::delete('/negocio/horarios/{id}', [PerfilNegocioController::class, 'eliminarHorario']);
    Route::get('/negocio/logo', [PerfilNegocioController::class, 'obtenerLogo']);
    Route::post('/negocio/foto-perfil', [PerfilNegocioController::class, 'actualizarFotoPerfil']);
    Route::get('/negocio/datos', [PerfilNegocioController::class, 'obtenerDatosNegocio']);

    // categorias

    Route::get('/categoria/web/{id_empresa}', [CategoriaController::class, 'obtenerCategories']);
    Route::post('/categoria/web', [CategoriaController::class, 'crearCategoria']);
    Route::put('/categoria/web/{id}', [CategoriaController::class, 'actualizarCategoria']);
    Route::delete('/categoria/web/{id}/{id_empresa}', [CategoriaController::class, 'eliminarCategoria']);
    Route::post('/categoria/web/reordenar', [CategoriaController::class, 'reordenar']);

    // Crear menus

    Route::post('/crear/menus/web', [MenuController::class, 'store']);
    Route::get('/listar/menus/web/{empresa_id}', [MenuController::class, 'index']);
    Route::put('/menu/web/{id}/status', [MenuController::class, 'updateStatus']);
    Route::put('/menus/web/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/web/{id}', [MenuController::class, 'destroy']);
    Route::post('/menus/web/reordenar', [MenuController::class, 'reordenarMenus']);

    // Rutas para adicionales
    Route::get('/adicionales/web/{empresa_id}', [AdicionalController::class, 'obtenerAdicionales']);
    Route::post('/adicionales/web', [AdicionalController::class, 'crearAdicional']);
    Route::get('/adicionales/web/detalle/{adicional}', [AdicionalController::class, 'obtenerAdicional']);
    Route::put('/adicionales/web/{adicional}', [AdicionalController::class, 'actualizarAdicional']);
    Route::delete('/adicionales/web/{adicional}', [AdicionalController::class, 'eliminarAdicional']);
    Route::post('/account/request-deletion', [AccountDeletionController::class, 'requestDeletion']);
});

Route::post('/register/check-status', [RegistrationStatusController::class, 'checkStatus']);
Route::get('/register/{id}/status', [RegistrationStatusController::class, 'getRegistrationStatus']);
// En routes/api.php
Route::post('/register/{id}/reset', [RegistrationStatusController::class, 'resetRegistration']);

// Rutas para descuentos de clientes
Route::get('descuentos/clientes', [DescuentoClienteController::class, 'index']);
Route::post('descuentos/clientes', [DescuentoClienteController::class, 'store']);
Route::get('descuentos/clientes/{id}', [DescuentoClienteController::class, 'show']);
Route::put('descuentos/clientes/{id}', [DescuentoClienteController::class, 'update']);
Route::delete('descuentos/clientes/{id}', [DescuentoClienteController::class, 'destroy']);
Route::get('descuentos/cliente/{idCliente}', [DescuentoClienteController::class, 'clienteDescuentos']);
Route::post('descuentos/aplicar', [DescuentoClienteController::class, 'aplicarDescuento']);
Route::get('clientes/top-completados', [DescuentoClienteController::class, 'getTopClientsWithCompletedOrders']);
Route::get('descuentos/estadisticas', [DescuentoClienteController::class, 'getEstadisticasDescuentos']);
Route::get('clientes/buscar', [DescuentoClienteController::class, 'buscarClientes']);

Route::post('/reparto/registro-completo', [RepartoRegistroCompletoController::class, 'registroCompleto']);
Route::post('/reparto/validate-document-email', [RepartoRegistroController::class, 'validateDocumentAndEmail']);

// Ruta para formulario de soporte
Route::post('/soporte/enviar', [App\Http\Controllers\SoporteController::class, 'enviarConsulta']);
