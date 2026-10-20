<?php

namespace App\Providers;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('CONSULTAR_ROLES', fn (UsuarioSistema $usuario): bool => $usuario->tieneAccesoContable()
            && array_intersect(['USUARIOS_VER', 'USUARIOS_CREAR', 'ROLES_GESTIONAR'], $usuario->permisosContables()) !== []);
        Gate::define('CONSULTAR_INVENTARIO', fn (UsuarioSistema $usuario): bool => $usuario->tieneAccesoContable()
            && array_intersect(['INVENTARIO_VER', 'INVENTARIO_GESTIONAR'], $usuario->permisosContables()) !== []);
        foreach (['MOVIMIENTOS_GESTIONAR', 'INVENTARIO_VER', 'INVENTARIO_GESTIONAR', 'VENTAS_VER', 'VENTAS_REGISTRAR', 'USUARIOS_VER', 'USUARIOS_CREAR', 'ROLES_GESTIONAR'] as $permiso) {
            Gate::define($permiso, fn (UsuarioSistema $usuario): bool => $usuario->tieneAccesoContable() && in_array($permiso, $usuario->permisosContables(), true));
        }

        RateLimiter::for('login-contable', function (Request $request): array {
            $usuario = $request->input('usuario');
            $key = Str::lower(is_string($usuario) ? trim($usuario) : '').'|'.$request->ip();

            return [
                Limit::perMinute(5)->by('login:'.$key)->response(
                    fn (Request $request, array $headers) => response()->json([
                        'message' => 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.',
                    ], 429, $headers),
                ),
                Limit::perMinute(30)->by('login-ip:'.$request->ip()),
            ];
        });
    }
}
