<?php

namespace App\Flows\Usuarios\Http;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PacientesUsuariosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'],
            'estado' => ['sometimes', 'in:ACTIVO,INACTIVO'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = UsuarioSistema::where('tipo_usuario', 'PACIENTE');
        if (isset($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        }
        if (! empty($datos['buscar'])) {
            $busqueda = '%'.$datos['buscar'].'%';
            $query->where(fn (Builder $query) => $query->whereLike('nombres', $busqueda)
                ->orWhereLike('apellidos', $busqueda)->orWhereLike('ci', $busqueda)->orWhereLike('usuario', $busqueda));
        }

        return response()->json($query->select(['id', 'nombres', 'apellidos', 'ci', 'usuario', 'estado'])
            ->latest()->orderBy('id')->paginate($datos['por_pagina'] ?? 20));
    }

    public function store(RegistrarPacienteUsuarioRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $rol = DB::table('roles')->where('codigo', 'PACIENTE_PORTAL')->where('activo', true)->first();
        abort_unless($rol, 422, 'El rol de acceso de pacientes no está habilitado.');

        $usuario = DB::transaction(function () use ($datos, $rol): UsuarioSistema {
            $usuario = UsuarioSistema::create([
                'tipo_usuario' => 'PACIENTE',
                'nombres' => $datos['nombre'],
                'ci' => $datos['ci'],
                'usuario' => $datos['usuario'],
                'password' => $datos['password'],
                'rol' => $rol->nombre,
                'estado' => 'ACTIVO',
            ]);
            DB::table('rol_usuario')->insert(['usuario_id' => $usuario->id, 'rol_id' => $rol->id]);

            return $usuario;
        });

        return response()->json(['data' => $usuario->only(['id', 'nombres', 'apellidos', 'ci', 'usuario', 'estado'])], 201);
    }
}
