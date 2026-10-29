<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Enlaces para compartir restaurantes y verificación de App Links / Universal Links.
Route::get('/l/{id}', [\App\Http\Controllers\EnlaceLocalController::class, 'show'])->whereNumber('id');
Route::get('/.well-known/assetlinks.json', [\App\Http\Controllers\EnlaceLocalController::class, 'assetLinks']);
Route::get('/.well-known/apple-app-site-association', [\App\Http\Controllers\EnlaceLocalController::class, 'appleAppSiteAssociation']);
