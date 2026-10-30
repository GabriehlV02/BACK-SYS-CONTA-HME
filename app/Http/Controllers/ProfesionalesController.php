<?php

namespace App\Http\Controllers;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Http\Requests\GuardarProfesionalRequest;
use App\Models\Profesional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfesionalesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['activo' => ['sometimes', 'boolean']]);

        return response()->json(['data' => Profesional::with('usuario:id,usuario,nombres,apellidos,ci')
            ->when(isset($datos['activo']), fn ($q) => $q->where('activo', $datos['activo']))
            ->orderBy('apellidos')->orderBy('nombres')->orderBy('id')->get()]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json(['usuarios' => UsuarioSistema::query()->where('tipo_usuario', 'EMPRESA')
            ->orderBy('nombres')->get(['id', 'nombres', 'apellidos', 'ci', 'usuario', 'estado'])]);
    }

    public function store(GuardarProfesionalRequest $request): JsonResponse
    {
        return response()->json(['data' => Profesional::create($this->datos($request))->refresh()->load('usuario:id,usuario,nombres,apellidos,ci')], 201);
    }

    public function update(GuardarProfesionalRequest $request, Profesional $profesional): JsonResponse
    {
        $profesional->update($this->datos($request));

        return response()->json(['data' => $profesional->fresh(['usuario:id,usuario,nombres,apellidos,ci'])]);
    }

    private function datos(GuardarProfesionalRequest $request): array
    {
        $datos = $request->validated();
        $usuario = UsuarioSistema::where('tipo_usuario', 'EMPRESA')->findOrFail($datos['usuario_id']);

        return [...$datos, 'nombres' => $usuario->nombres, 'apellidos' => $usuario->apellidos ?? '',
            'ci' => filled($usuario->ci) ? mb_strtoupper(trim($usuario->ci)) : null,
            'especialidades' => $datos['especialidades'] ?? [],
            'rol' => filled($datos['rol'] ?? null) ? $datos['rol'] : $datos['tipo'],
            'matricula' => $datos['matricula'] ?? null];
    }
}
