<?php

use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DatosBancariosRepartoController;
use App\Http\Controllers\MotorizadoController;
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
use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;


Route::post('/admin/login', [AuthAdminController::class, 'login']);



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/user', [UserController::class, 'all']);
    Route::post('/admin/users/change/state/{id}', [UserController::class, 'changeState']);
    Route::post('/admin/users/create', [UserController::class, 'store']);

    Route::get('/admin/socio', [SocioController::class, 'all']);
    Route::post('/admin/socio/change/state/{id}', [SocioController::class, 'changeState']);
    Route::get('/admin/socio/{id}/details', [SocioController::class, 'getDetails']);
    Route::post('/admin/socio/{id}/aprobar', [SocioController::class, 'aprobar']);

    Route::get('/admin/motorizado', [MotorizadoController::class, 'all']);
    Route::post('/admin/motorizado/change/state/{id}', [MotorizadoController::class, 'changeState']);
    Route::get('/admin/motorizado/{id}/details', [MotorizadoController::class, 'getDetails']);
    Route::post('/admin/motorizado/{id}/aprobar', [MotorizadoController::class, 'aprobar']);
});



// Rutas de autenticación y verificación
Route::controller(EmailVerificationController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/verify', 'verify');
    Route::post('/resend-code', 'resendCode');
});

// Otras rutas
Route::post('/negocios', [NegocioController::class, 'store']);

Route::get('/tipos-negocio', [NegocioController::class, 'getTiposNegocio']);
Route::get('/categorias/{tipoNegocioId}', [NegocioController::class, 'getCategorias']);
Route::post('/negocios', [NegocioController::class, 'store']);
Route::put('/negocios/{negocio}', [NegocioController::class, 'update']);
Route::post('/negocios/{negocio}/sucursales', [SucursalController::class, 'store']);


Route::post('/establecimientos', [EstablecimientoController::class, 'store']);
Route::put('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'update']);
Route::get('/obtener-ultimos-ids', [IdsController::class, 'obtenerUltimosIds']);
Route::get('/establecimientos', [EstablecimientoController::class, 'index']);
Route::get('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'show']);
Route::delete('/establecimientos/{establecimiento}', [EstablecimientoController::class, 'destroy']);


Route::get('/revisarDatos', [RevisarDatosController::class, 'obtenerDatosRevision']);
Route::get('/revisarDatos/{negocioId}', [RevisarDatosController::class, 'obtenerDatosRevision']);

Route::post('/datos-bancarios', [DatosBancariosController::class, 'store']);
Route::get('/establecimiento/{id}/direccion', [DatosBancariosController::class, 'getEstablecimientoDireccion']);

Route::post('/datos-clave-negocio', [DatosClaveNegocioController::class, 'guardar']);

Route::post('/reparto/registro', [RepartoRegistroController::class, 'store']);

Route::get('/ciudades', [DatosPersonalesRepartoController::class, 'obtenerCiudades']);
Route::get('/distritos/{ciudadId}', [DatosPersonalesRepartoController::class, 'obtenerDistritos']);
Route::post('/datos-personales', [DatosPersonalesRepartoController::class, 'guardar']);


Route::get('/bancos', [DatosBancariosRepartoController::class, 'obtenerBancos']);
Route::get('/tipos-cuenta', [DatosBancariosRepartoController::class, 'obtenerTiposCuenta']);
Route::post('/cuenta-bancaria', [DatosBancariosRepartoController::class, 'guardarCuentaBancaria']);


//rutas app clientes
Route::post('/send-code', [ClienteController::class, 'sendCode']);
Route::post('/profile', [ClienteController::class, 'store']);
Route::post('/get-dni', [ClienteController::class, 'getDni']);
Route::post('/update-profile', [ClienteController::class, 'actualizarInfoCliente']);
Route::post('/send-code-phone', [ClienteController::class, 'sendCodePhone']);

Route::post('/registro-vehiculo', [RegistroVehiculoController::class, 'guardar']);
Route::get('/registro-vehiculo/{id}', [RegistroVehiculoController::class, 'mostrar']);
Route::get('/registros-vehiculos', [RegistroVehiculoController::class, 'listar']);

Route::post('/socios/cuenta-bancaria', [SociosCuentaBancariaController::class, 'store']);


//rutas app socios
Route::get('/categories/{id_empresa}', [CategoriaController::class, 'index']);
Route::post('/categories', [CategoriaController::class, 'store']);
Route::put('/categories/{id}', [CategoriaController::class, 'update']);
Route::delete('/categorias/{id}/{id_empresa}', [CategoriaController::class, 'destroy']);

Route::post('/crear/menus', [MenuController::class, 'store']);
