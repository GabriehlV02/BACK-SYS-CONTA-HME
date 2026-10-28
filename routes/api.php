<?php

use App\Flows\Clientes\Http\ClientesController;
use App\Flows\Inventario\Http\AdquisicionesController;
use App\Flows\Inventario\Http\CajasController;
use App\Flows\Inventario\Http\ConfiguracionGeneralController;
use App\Flows\Inventario\Http\ConfiguracionInventarioController;
use App\Flows\Inventario\Http\GuardarConfiguracionInventarioRequest;
use App\Flows\Inventario\Http\ItemsController;
use App\Flows\Inventario\Http\ProductosBaseController;
use App\Flows\Inventario\Http\ServiciosController;
use App\Flows\Inventario\Http\StockController;
use App\Flows\Usuarios\Http\PacientesUsuariosController;
use App\Flows\Usuarios\Http\RolesController;
use App\Flows\Usuarios\Http\SesionController;
use App\Flows\Usuarios\Http\UsuariosController;
use App\Flows\Usuarios\Http\VerificarAccesoContable;
use App\Flows\Ventas\Http\CatalogoVentasController;
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
    Route::get('configuracion/cajas', [CajasController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('configuracion/cajas', [CajasController::class, 'store'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('configuracion/general', [ConfiguracionGeneralController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('configuracion/sucursales', [ConfiguracionGeneralController::class, 'storeSucursal'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::post('configuracion/almacenes', [ConfiguracionGeneralController::class, 'storeAlmacen'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::post('configuracion/dispositivos', [ConfiguracionGeneralController::class, 'storeDispositivo'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('adquisiciones', [AdquisicionesController::class, 'index'])->middleware('can:MOVIMIENTOS_GESTIONAR');
    Route::get('adquisiciones/opciones', [AdquisicionesController::class, 'opciones'])->middleware('can:MOVIMIENTOS_GESTIONAR');
    Route::post('adquisiciones', [AdquisicionesController::class, 'store'])->middleware('can:MOVIMIENTOS_GESTIONAR');
    Route::post('adquisiciones/almacenes', [AdquisicionesController::class, 'storeAlmacen'])->middleware('can:MOVIMIENTOS_GESTIONAR');
    Route::get('stock/estudios', [AdquisicionesController::class, 'estudios'])->middleware('can:MOVIMIENTOS_GESTIONAR');
    Route::get('stock/lotes', [StockController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::get('inventario/items/opciones', [ItemsController::class, 'opciones'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::get('inventario/items', [ItemsController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/items', [ItemsController::class, 'store'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::put('inventario/items/{item}', [ItemsController::class, 'update'])->whereNumber('item')->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::post('inventario/items/{item}/lotes', [ItemsController::class, 'storeLote'])->whereNumber('item')->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::put('inventario/items/{item}/lotes/{lote}', [ItemsController::class, 'updateLote'])->whereNumber('item')->whereNumber('lote')->scopeBindings()->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('inventario/servicios', [ServiciosController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/servicios', [ServiciosController::class, 'store'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::put('inventario/servicios/{servicio}', [ServiciosController::class, 'update'])->whereNumber('servicio')->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('inventario/productos-base', [ProductosBaseController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/productos-base', [ProductosBaseController::class, 'store'])->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::put('inventario/productos-base/{producto}', [ProductosBaseController::class, 'update'])->whereNumber('producto')->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('inventario/configuracion', [ConfiguracionInventarioController::class, 'index'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::get('inventario/opciones', [ConfiguracionInventarioController::class, 'opciones'])->middleware('can:CONSULTAR_INVENTARIO');
    Route::post('inventario/configuracion/{catalogo}', [ConfiguracionInventarioController::class, 'store'])
        ->whereIn('catalogo', array_keys(GuardarConfiguracionInventarioRequest::MODELOS))->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::patch('inventario/configuracion/{catalogo}/{registro}', [ConfiguracionInventarioController::class, 'update'])
        ->whereIn('catalogo', array_keys(GuardarConfiguracionInventarioRequest::MODELOS))->whereUuid('registro')->middleware(['can:INVENTARIO_GESTIONAR', 'can:ADMINISTRAR_CATALOGO']);
    Route::get('clientes', [ClientesController::class, 'index'])->middleware('can:VENTAS_VER');
    Route::post('clientes', [ClientesController::class, 'store'])->middleware('can:VENTAS_REGISTRAR');
    Route::get('ventas', [VentasController::class, 'index'])->middleware('can:VENTAS_VER');
    Route::get('ventas/catalogo', CatalogoVentasController::class)->middleware('can:VENTAS_REGISTRAR');
    Route::post('ventas', [VentasController::class, 'store'])->middleware('can:VENTAS_REGISTRAR');
    Route::get('usuarios', [UsuariosController::class, 'index'])->middleware('can:USUARIOS_VER');
    Route::get('usuarios/opciones', [UsuariosController::class, 'opciones'])->middleware('can:USUARIOS_VER');
    Route::post('usuarios', [UsuariosController::class, 'store'])->middleware('can:USUARIOS_CREAR');
    Route::get('pacientes-usuarios', [PacientesUsuariosController::class, 'index'])->middleware('can:USUARIOS_VER');
    Route::post('pacientes-usuarios', [PacientesUsuariosController::class, 'store'])->middleware('can:USUARIOS_CREAR');
    Route::put('usuarios/{usuario}', [UsuariosController::class, 'update'])->whereUuid('usuario')->middleware('can:USUARIOS_CREAR');
    Route::get('roles', [RolesController::class, 'index'])->middleware('can:CONSULTAR_ROLES');
    Route::post('roles', [RolesController::class, 'store'])->middleware('can:ROLES_GESTIONAR');
    Route::put('roles/{rol}', [RolesController::class, 'update'])->whereUuid('rol')->middleware('can:ROLES_GESTIONAR');
});
