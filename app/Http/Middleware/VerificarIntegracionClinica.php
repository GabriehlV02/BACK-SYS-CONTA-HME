<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarIntegracionClinica
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.clinico.token');
        abort_unless(is_string($token) && strlen($token) >= 32 && hash_equals($token, $request->bearerToken() ?? ''), 401, 'Integración no autorizada.');

        return $next($request);
    }
}
