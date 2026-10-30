<?php

namespace App\Flows\Clientes;

use App\Models\Cliente;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PacientesClinicos
{
    public function vincular(array $paciente): Cliente
    {
        return DB::transaction(function () use ($paciente): Cliente {
            $cliente = Cliente::query()->where('paciente_id', $paciente['id'])->first()
                ?? Cliente::query()->where('nit_ci', $paciente['ci'])->first();
            abort_if($cliente?->paciente_id && $cliente->paciente_id !== $paciente['id'], 422, 'El documento ya está vinculado a otro paciente.');
            $cliente ??= new Cliente;
            $cliente->fill(['paciente_id' => $paciente['id'], 'nit_ci' => $paciente['ci'],
                'nombre_razon_social' => trim($paciente['nombres'].' '.$paciente['apellido_paterno'].' '.$paciente['apellido_materno']),
                'telefono' => $paciente['telefono'] ?? null, 'activo' => true]);
            $cliente->save();

            return $cliente;
        });
    }

    public function consultar(string $pacienteId): array
    {
        $url = config('services.clinico.url');
        $token = config('services.clinico.token');
        abort_unless($url && $token, 503, 'La conexión con el sistema clínico no está configurada.');
        try {
            $respuesta = Http::acceptJson()->withToken($token)->connectTimeout(3)->timeout(10)
                ->get(rtrim($url, '/').'/api/integraciones/contable/pacientes', ['paciente_id' => $pacienteId, 'por_pagina' => 1]);
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar con el sistema clínico. Intenta nuevamente.');
        }
        abort_unless($respuesta->successful(), 503, 'No se pudo verificar al paciente en el sistema clínico.');
        $paciente = $respuesta->json('data.0');
        abort_unless(is_array($paciente) && ($paciente['id'] ?? null) === $pacienteId, 422, 'Selecciona un paciente registrado en el sistema clínico.');

        return $paciente;
    }
}
