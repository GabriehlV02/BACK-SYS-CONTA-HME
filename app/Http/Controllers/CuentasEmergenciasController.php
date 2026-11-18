<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class CuentasEmergenciasController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $url = config('services.clinico.url');
        $token = config('services.clinico.token');
        abort_unless($url && $token, 503, 'La conexión con el sistema clínico no está configurada.');
        try {
            $respuesta = Http::acceptJson()->withToken($token)->connectTimeout(3)->timeout(15)
                ->get(rtrim($url, '/').'/api/integraciones/contable/cuentas-emergencias');
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar con el sistema clínico. Intenta nuevamente.');
        }
        abort_unless($respuesta->successful() && is_array($respuesta->json('data')), 503, 'El sistema clínico no está disponible. Intenta nuevamente.');

        return response()->json(['data' => $respuesta->json('data')])->header('Cache-Control', 'no-store');
    }
}
