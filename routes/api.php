<?php

use App\Flows\Clientes\Http\ClientesController;
use App\Flows\Inventario\Http\ConfiguracionInventarioController;
use App\Flows\Inventario\Http\GuardarConfiguracionInventarioRequest;
use App\Flows\Inventario\Http\ProductosBaseController;
use App\Flows\Usuarios\Http\PacientesUsuariosController;
use App\Flows\Usuarios\Http\RolesController;
use App\Flows\Usuarios\Http\SesionController;
use App\Flows\Usuarios\Http\UsuariosController;
use App\Flows\Usuarios\Http\VerificarAccesoContable;
use App\Flows\Ventas\Http\VentasController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('csrf', [SesionController::class, 'csrf']);
    Route::post('login', [SesionController::class, 'store'])->middleware('throttle:login-contable');
    Route::post('logout', [SesionController::class, 'destroy']);
    Route::get('session', [SesionController::class, 'show'])
        ->middleware(['auth:contable', VerificarAccesoContable::class]);
});

Route::prefix('v1')->middleware(['web', 'auth:contable', VerificarAccesoContable::class])->group(function (): void {
    Route::get('inventario/productos-base', [ProductosBaseController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/productos-base', [ProductosBaseController::class, 'store'])->middleware('can:INVENTARIO_GESTIONAR');
    Route::put('inventario/productos-base/{producto}', [ProductosBaseController::class, 'update'])->whereNumber('producto')->middleware('can:INVENTARIO_GESTIONAR');
    Route::get('inventario/configuracion', [ConfiguracionInventarioController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::get('inventario/opciones', [ConfiguracionInventarioController::class, 'opciones'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/configuracion/{catalogo}', [ConfiguracionInventarioController::class, 'store'])
        ->whereIn('catalogo', array_keys(GuardarConfiguracionInventarioRequest::MODELOS))->middleware('can:INVENTARIO_GESTIONAR');
    Route::patch('inventario/configuracion/{catalogo}/{registro}', [ConfiguracionInventarioController::class, 'update'])
        ->whereIn('catalogo', array_keys(GuardarConfiguracionInventarioRequest::MODELOS))->whereUuid('registro')->middleware('can:INVENTARIO_GESTIONAR');
    Route::get('clientes', [ClientesController::class, 'index'])->middleware('can:VENTAS_VER');
    Route::post('clientes', [ClientesController::class, 'store'])->middleware('can:VENTAS_REGISTRAR');
    Route::get('ventas', [VentasController::class, 'index'])->middleware('can:VENTAS_VER');
    Route::post('ventas', [VentasController::class, 'store'])->middleware('can:VENTAS_REGISTRAR');
    Route::get('usuarios', [UsuariosController::class, 'index'])->middleware('can:USUARIOS_VER');
    Route::post('usuarios', [UsuariosController::class, 'store'])->middleware('can:USUARIOS_CREAR');
    Route::get('pacientes-usuarios', [PacientesUsuariosController::class, 'index'])->middleware('can:USUARIOS_VER');
    Route::post('pacientes-usuarios', [PacientesUsuariosController::class, 'store'])->middleware('can:USUARIOS_CREAR');
    Route::put('usuarios/{usuario}', [UsuariosController::class, 'update'])->whereUuid('usuario')->middleware('can:USUARIOS_CREAR');
    Route::get('roles', [RolesController::class, 'index'])->middleware('can:CONSULTAR_ROLES');
    Route::post('roles', [RolesController::class, 'store'])->middleware('can:ROLES_GESTIONAR');
    Route::put('roles/{rol}', [RolesController::class, 'update'])->whereUuid('rol')->middleware('can:ROLES_GESTIONAR');
});
