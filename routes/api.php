<?php

use App\Http\Controllers\Api\NotificacionCitaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/notificaciones-citas', [NotificacionCitaController::class, 'publicar']);
Route::post('/notificaciones-citas/sincrono', [NotificacionCitaController::class, 'sincrono']);
Route::get('/notificaciones-citas/{correlationId}', [NotificacionCitaController::class, 'consultar']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
