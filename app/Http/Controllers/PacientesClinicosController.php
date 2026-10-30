<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PacientesClinicosController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $url = config('services.clinico.url');
        $token = config('services.clinico.token');
        abort_unless($url && $token, 503, 'La conexión con el sistema clínico no está configurada.');
        try {
            $respuesta = Http::acceptJson()->withToken($token)->connectTimeout(3)->timeout(15)
                ->send($request->method(), rtrim($url, '/').'/api/integraciones/contable/pacientes', [
                    $request->isMethod('GET') ? 'query' : 'json' => $request->all(),
                ]);
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar con el sistema clínico. Intenta nuevamente.');
        }
        if ($respuesta->status() === 422) {
            return response()->json($respuesta->json(), 422);
        }
        abort_unless($respuesta->successful() && is_array($respuesta->json()), 503, 'El sistema clínico no está disponible. Intenta nuevamente.');

        return response()->json($respuesta->json(), $respuesta->status())->header('Cache-Control', 'no-store');
    }
}
