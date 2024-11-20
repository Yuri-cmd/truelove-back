<?php

use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\SocioController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthAdminController::class, 'login']);

Route::post('/register', [EmailVerificationController::class, 'register']);
Route::post('/verify', [EmailVerificationController::class, 'verify']);
Route::post('/resend-code', [EmailVerificationController::class, 'resendCode']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/user', [UserController::class, 'all']);
    Route::post('/admin/users/change/state/{id}', [UserController::class, 'changeState']);
    Route::post('/admin/users/create', [UserController::class, 'store']);

    Route::get('/admin/socio', [SocioController::class, 'all']);
    Route::post('/admin/socio/change/state/{id}', [SocioController::class, 'changeState']);
});
