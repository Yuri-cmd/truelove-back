<?php

use App\Http\Controllers\AuthAdminController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthAdminController::class, 'login']);
Route::get('/admin/user', [UserController::class, 'all']);
Route::post('/admin/users/change/state/{id}', [UserController::class, 'changeState']);
Route::post('/admin/users/create', [UserController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {

});