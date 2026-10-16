<?php

use App\Flows\Clientes\Http\ClientesController;
use App\Flows\Usuarios\Http\RolesController;
use App\Flows\Usuarios\Http\UsuariosController;
use App\Flows\Ventas\Http\VentasController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::post('login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate(['usuario' => ['required', 'string'], 'password' => ['required', 'string']]);

    if (! env('TEMPORARY_ADMIN_LOGIN', false) || $credentials['usuario'] !== 'admin' || $credentials['password'] !== 'admin') {
        return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 401);
    }

    return response()->json(['token' => Str::random(64), 'usuario' => ['nombre' => 'Administrador', 'rol' => 'Administrador', 'permisos' => []]]);
});

Route::prefix('v1')->group(function (): void {
    Route::get('clientes', [ClientesController::class, 'index']);
    Route::post('clientes', [ClientesController::class, 'store']);
    Route::get('ventas', [VentasController::class, 'index']);
    Route::post('ventas', [VentasController::class, 'store']);
    Route::get('usuarios', [UsuariosController::class, 'index']);
    Route::post('usuarios', [UsuariosController::class, 'store']);
    Route::post('roles', [RolesController::class, 'store']);
});
