<?php
use App\Flows\Usuarios\Http\UsuariosController;
use App\Flows\Usuarios\Http\RolesController;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function (): void {
    Route::get('usuarios', [UsuariosController::class, 'index']);
    Route::post('usuarios', [UsuariosController::class, 'store']);
    Route::post('roles', [RolesController::class, 'store']);
});
