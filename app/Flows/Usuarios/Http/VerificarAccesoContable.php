<?php

namespace App\Flows\Usuarios\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarAccesoContable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('contable')?->tieneAccesoContable()) {
            Auth::guard('contable')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Tu acceso al sistema contable ya no está habilitado.'], 401);
        }

        return $next($request);
    }
}
