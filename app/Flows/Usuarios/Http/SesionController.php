<?php

namespace App\Flows\Usuarios\Http;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SesionController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(IniciarSesionRequest $request): JsonResponse
    {
        $guard = Auth::guard('contable');
        $credentials = [...$request->validated(), 'estado' => 'ACTIVO', 'tipo_usuario' => 'EMPRESA'];

        if (! $guard->attemptWhen($credentials, fn (UsuarioSistema $usuario): bool => $usuario->tieneAccesoContable())) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos, o acceso no habilitado.'], 401);
        }

        $request->session()->regenerate();

        return $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'usuario' => (new SesionResource($request->user('contable')))->resolve($request),
        ])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('contable')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
