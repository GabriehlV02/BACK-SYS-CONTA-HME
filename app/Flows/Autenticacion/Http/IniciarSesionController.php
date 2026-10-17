<?php

namespace App\Flows\Autenticacion\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class IniciarSesionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $credenciales = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $usuario = DB::table('usuarios_sistema')
            ->where('usuario', $credenciales['usuario'])
            ->where('estado', 'ACTIVO')
            ->first();

        if (! $usuario || ! Hash::check($credenciales['password'], $usuario->password)) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 422);
        }

        $acceso = DB::table('usuario_sistema as us')
            ->join('sistemas as s', 's.id', '=', 'us.sistema_id')
            ->where('us.usuario_id', $usuario->id)
            ->where('s.codigo', 'CONTABLE')
            ->where('s.activo', true)
            ->first(['us.id', 'us.activo']);

        if (! $acceso || ! $acceso->activo) {
            return response()->json(['message' => 'Tu acceso al sistema contable está desactivado.'], 403);
        }

        $roles = DB::table('roles as r')
            ->join('rol_usuario_sistema as rus', 'rus.rol_id', '=', 'r.id')
            ->where('rus.usuario_sistema_id', $acceso->id)
            ->where('r.activo', true)
            ->get(['r.id', 'r.nombre']);

        $permisos = DB::table('permisos as p')
            ->join('permiso_rol as pr', 'pr.permiso_id', '=', 'p.id')
            ->join('rol_usuario_sistema as rus', 'rus.rol_id', '=', 'pr.rol_id')
            ->where('rus.usuario_sistema_id', $acceso->id)
            ->where('p.activo', true)
            ->distinct()
            ->pluck('p.codigo');

        $excepciones = DB::table('permiso_usuario_sistema as pus')
            ->join('permisos as p', 'p.id', '=', 'pus.permiso_id')
            ->where('pus.usuario_sistema_id', $acceso->id)
            ->where('p.activo', true)
            ->get(['p.codigo', 'pus.efecto']);

        foreach ($excepciones as $excepcion) {
            if ($excepcion->efecto === 'DENEGAR') {
                $permisos = $permisos->reject(fn (string $codigo) => $codigo === $excepcion->codigo);
            } elseif ($excepcion->efecto === 'CONCEDER' && ! $permisos->contains($excepcion->codigo)) {
                $permisos->push($excepcion->codigo);
            }
        }

        $permisos = $permisos->unique()
            ->map(fn (string $codigo) => strtolower(str_replace('_', '.', $codigo)))
            ->values();

        return response()->json([
            'token' => Str::random(64),
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => trim("{$usuario->nombres} {$usuario->apellidos}"),
                'rol' => $roles->first()?->nombre ?? $usuario->rol ?? 'Sin rol',
                'permisos' => $permisos,
            ],
            'asignacion' => null,
        ]);
    }
}
