<?php

use App\Http\Controllers\AuthAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/admin/login', [AuthAdminController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

});