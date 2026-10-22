<?php

namespace App\Flows\Usuarios\Http;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsuariosController
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_usuario' => ['sometimes', 'in:EMPRESA,PACIENTE'], 'estado' => ['sometimes', 'in:ACTIVO,INACTIVO'],
            'buscar' => ['sometimes', 'nullable', 'string', 'max:100'], 'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = UsuarioSistema::with(['sucursalesAsignadas', 'almacenesAsignados'])->where('tipo_usuario', $datos['tipo_usuario'] ?? 'EMPRESA');
        if (isset($datos['estado'])) {
            $query->where('estado', $datos['estado']);
        }
        if (! empty($datos['buscar'])) {
            $busqueda = '%'.$datos['buscar'].'%';
            $query->where(fn (Builder $query) => $query->where('nombres', 'like', $busqueda)
                ->orWhere('apellidos', 'like', $busqueda)->orWhere('usuario', 'like', $busqueda)
                ->orWhereIn('id', DB::table('rol_usuario')->join('roles', 'roles.id', '=', 'rol_usuario.rol_id')->where('roles.nombre', 'like', $busqueda)->select('usuario_id')));
        }
        $pagina = $query->latest()->orderBy('id')->paginate($datos['por_pagina'] ?? 20);
        $roles = DB::table('rol_usuario')->join('roles', 'roles.id', '=', 'rol_usuario.rol_id')
            ->whereIn('usuario_id', $pagina->getCollection()->pluck('id'))->get(['usuario_id', 'roles.id', 'roles.nombre', 'roles.codigo'])->groupBy('usuario_id');
        $pagina->getCollection()->transform(function (UsuarioSistema $usuario) use ($roles): UsuarioSistema {
            $usuario->setAttribute('roles', ($roles->get($usuario->id) ?? collect())->map(fn (object $rol): array => [
                'id' => $rol->id, 'nombre' => $rol->nombre, 'codigo' => $rol->codigo,
            ])->values()->all());

            return $usuario;
        });

        return response()->json($pagina);
    }

    public function store(RegistrarUsuarioRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = DB::transaction(function () use ($datos): UsuarioSistema {
            $usuario = UsuarioSistema::create([...Arr::except($datos, ['rol_id', 'password_confirmation', 'sucursal_ids', 'almacen_ids']), 'estado' => $datos['estado'] ?? 'ACTIVO']);
            $this->asignarUbicaciones($usuario, $datos);
            if (! empty($datos['rol_id'])) {
                $this->asignarRol($usuario, $datos['rol_id']);
            }

            return $usuario;
        });

        return response()->json(['data' => $usuario->load(['sucursalesAsignadas', 'almacenesAsignados'])], 201);
    }

    public function update(ActualizarUsuarioRequest $request, string $usuario): JsonResponse
    {
        $registro = UsuarioSistema::where('tipo_usuario', 'EMPRESA')->findOrFail($usuario);
        $datos = $request->validated();
        $rolAdmin = $registro->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->value('roles.id');
        if ($rolAdmin && ($datos['rol_id'] !== $rolAdmin || ($datos['estado'] ?? $registro->estado) !== 'ACTIVO')) {
            throw ValidationException::withMessages(['rol_id' => 'El acceso del administrador inicial está protegido.']);
        }
        if ($registro->id === $request->user('contable')->id && ($datos['estado'] ?? 'ACTIVO') !== 'ACTIVO') {
            throw ValidationException::withMessages(['estado' => 'No puedes desactivar tu propia sesión.']);
        }
        DB::transaction(function () use ($registro, $datos): void {
            $atributos = Arr::except($datos, ['rol_id', 'password_confirmation', 'sucursal_ids', 'almacen_ids']);
            if (empty($atributos['password'])) {
                unset($atributos['password']);
            }
            $registro->update($atributos);
            $this->asignarUbicaciones($registro, $datos);
            $this->asignarRol($registro, $datos['rol_id']);
        });

        return response()->json(['data' => $registro->fresh(['sucursalesAsignadas', 'almacenesAsignados'])]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json(['data' => [
            'sucursales' => SucursalInventario::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
            'almacenes' => AlmacenInventario::where('activo', true)->whereHas('sucursal', fn (Builder $q) => $q->where('activo', true))
                ->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'sucursal_id']),
        ]]);
    }

    private function asignarUbicaciones(UsuarioSistema $usuario, array $datos): void
    {
        if (array_key_exists('sucursal_ids', $datos)) {
            $usuario->sucursalesAsignadas()->sync($datos['sucursal_ids']);
            $usuario->almacenesAsignados()->sync($datos['almacen_ids'] ?? []);
            $usuario->update([
                'sucursal' => $usuario->sucursalesAsignadas()->orderBy('sucursales_inventario.id')->value('nombre'),
                'almacenes' => $usuario->almacenesAsignados()->orderBy('almacenes_inventario.id')->pluck('nombre')->all(),
            ]);
        }
    }

    private function asignarRol(UsuarioSistema $usuario, string $rol): void
    {
        DB::table('rol_usuario')->where('usuario_id', $usuario->id)->delete();
        DB::table('rol_usuario')->insert(['usuario_id' => $usuario->id, 'rol_id' => $rol]);
        $usuario->update(['rol' => DB::table('roles')->where('id', $rol)->value('nombre')]);
    }
}
