<?php

namespace App\Flows\Clientes\Http;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientesController
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:120']]);
        $termino = trim($datos['buscar'] ?? '');

        $clientes = Cliente::query()
            ->where('activo', true)
            ->when($termino !== '', function ($query) use ($termino): void {
                $query->where(function ($coincidencias) use ($termino): void {
                    $patron = "%{$termino}%";
                    $coincidencias
                        ->where('nombre_razon_social', 'like', $patron)
                        ->orWhere('nit_ci', 'like', $patron)
                        ->orWhere('telefono', 'like', $patron)
                        ->orWhere('correo', 'like', $patron);
                });
            })
            ->orderBy('nombre_razon_social')
            ->limit(20)
            ->get(['id', 'nit_ci', 'nombre_razon_social', 'telefono', 'correo']);

        return response()->json(['data' => $clientes]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nit_ci' => ['nullable', 'string', 'max:30', Rule::unique('clientes', 'nit_ci')],
            'nombre_razon_social' => ['required', 'string', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:180'],
        ]);

        $cliente = Cliente::query()->create([...$datos, 'activo' => true]);

        return response()->json([
            'data' => $cliente->only(['id', 'nit_ci', 'nombre_razon_social', 'telefono', 'correo']),
        ], 201);
    }
}
