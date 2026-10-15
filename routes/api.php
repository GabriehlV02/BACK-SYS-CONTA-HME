<?php

use App\Flows\Clientes\Http\ClientesController;
use App\Flows\Usuarios\Http\RolesController;
use App\Flows\Usuarios\Http\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('clientes', [ClientesController::class, 'index']);
    Route::post('clientes', [ClientesController::class, 'store']);
    Route::get('usuarios', [UsuariosController::class, 'index']);
    Route::post('usuarios', [UsuariosController::class, 'store']);
    Route::post('roles', [RolesController::class, 'store']);
});
