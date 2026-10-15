<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\ClienteDeletionAdminController;
use App\Http\Controllers\CuotaMotorizadoController;
use App\Http\Controllers\CuotaSocioController;
use App\Http\Controllers\DescuentoClienteController;
use App\Http\Controllers\EntregaCalendarioController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\InfoClienteController;
use App\Http\Controllers\KilometrosTarifaController;
use App\Http\Controllers\MotorizadoController;
use App\Http\Controllers\PedidoAdminController;
use App\Http\Controllers\PedidoCancelacionController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\PerfilNegocioController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\TarifaRangoController;
use App\Http\Controllers\TipoNegocioController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del panel de administración
|--------------------------------------------------------------------------
| Movidas tal cual desde routes/api.php (mismo método, mismo URI, mismo
| middleware) — solo se reorganizó en qué archivo viven.
*/

Route::post('/admin/login', [AuthAdminController::class, 'login']);

Route::post('/admin/verify-email', [AuthAdminController::class, 'verifyEmail']);
Route::post('/admin/verify-code', [AuthAdminController::class, 'verifyCode']);
Route::post('/admin/reset-password', [AuthAdminController::class, 'resetPassword']);

// Diagnóstico de notificaciones del repartidor (app biker), usado desde el panel admin
Route::post('/notifications/diagnostico/motorizado/{id}', [App\Http\Controllers\NotificationTrackingController::class, 'resumenMotorizado'])->middleware('throttle:30,1');
Route::post('/notifications/diagnostico/motorizado/{id}/probar', [App\Http\Controllers\NotificationTrackingController::class, 'probarMotorizado'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->get('/admin/check-auth', [AuthAdminController::class, 'checkAuth']);

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Revisión de notas y fotos de las casas (repartidores)
        Route::get('/entrega-notas', [App\Http\Controllers\AdminEntregaNotaController::class, 'index']);
        Route::post('/entrega-notas/{id}/aprobar', [App\Http\Controllers\AdminEntregaNotaController::class, 'aprobar']);
        Route::post('/entrega-notas/{id}/rechazar', [App\Http\Controllers\AdminEntregaNotaController::class, 'rechazar']);
        Route::delete('/entrega-notas/{id}', [App\Http\Controllers\AdminEntregaNotaController::class, 'destroy']);
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

        // Logs de notificaciones
        Route::get('/notification-logs', [App\Http\Controllers\NotificationTrackingController::class, 'index']);

        // Panel de mensajes de WhatsApp (accesible solo por URL directa, sin enlace en el menú)
        Route::get('/whatsapp/stats', [App\Http\Controllers\WhatsappStatsController::class, 'index']);

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

        // Fotos de negocios (logo/banner) asignadas por el admin
        Route::controller(PerfilNegocioController::class)->prefix('negocios')->group(function () {
            Route::post('/{businessId}/logo', 'actualizarLogoAdmin');
            Route::post('/{businessId}/banner', 'actualizarBannerAdmin');
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
            Route::post('/cliente/{id}/numero-validado', 'cambiarNumeroValidado');
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

        // Rutas para administración de Versiones de Apps
        Route::controller(AppVersionController::class)->prefix('app-versions')->group(function () {
            Route::get('/', 'index');
            Route::put('/{id}', 'updateAdmin');
        });

        // Deudas de clientes (pedidos no entregados por culpa del cliente)
        Route::controller(App\Http\Controllers\ClienteDeudaController::class)->prefix('deudas')->group(function () {
            Route::get('/', 'index');
            Route::get('/buscar-clientes', 'buscarClientes');
            Route::post('/', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'destroy');
        });

        // Solicitudes de cancelación de pedidos (aprobar/declinar).
        // Debe registrarse ANTES de "pedidos/{id}" para que "cancelacion-solicitudes"
        // no sea interpretado como un {id} de pedido.
        Route::controller(PedidoCancelacionController::class)->prefix('pedidos/cancelacion-solicitudes')->group(function () {
            Route::get('/', 'getPendingRequests');
            Route::get('/history', 'getRequestHistory');
            Route::post('/{id}/approve', 'approveRequest');
            Route::post('/{id}/reject', 'rejectRequest');
        });

        // Módulo de Pedidos (listado general y cambio de estado libre por el admin)
        Route::controller(PedidoAdminController::class)->prefix('pedidos')->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::put('/{id}/estado', 'updateEstado');
            Route::put('/{id}/fecha', 'updateFecha');
        });
    });
});

// Rankings/analítica (vistas desde el panel admin)
Route::get('/rankings/clients', [RatingController::class, 'getTopClients']);
Route::get('/rankings/stores', [RatingController::class, 'getTopStores']);
Route::get('/locales/ranking', [PedidoController::class, 'getLocalesPorPedidos']);

// Descuentos asignados a clientes (gestión admin)
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
