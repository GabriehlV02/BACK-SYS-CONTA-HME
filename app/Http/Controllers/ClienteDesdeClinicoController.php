<?php

namespace App\Http\Controllers;

use App\Flows\Clientes\PacientesClinicos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteDesdeClinicoController extends Controller
{
    public function __invoke(Request $request, PacientesClinicos $clinico): JsonResponse
    {
        $datos = $request->validate(['id' => ['required', 'uuid'], 'ci' => ['required', 'string', 'max:25'],
            'nombres' => ['required', 'string', 'max:100'], 'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['required', 'string', 'max:100'], 'telefono' => ['nullable', 'string', 'max:30']]);
        $cliente = $clinico->vincular($datos);

        return response()->json(['data' => $cliente->only(['id', 'paciente_id', 'nit_ci', 'nombre_razon_social', 'telefono', 'correo'])], 201);
    }
}
