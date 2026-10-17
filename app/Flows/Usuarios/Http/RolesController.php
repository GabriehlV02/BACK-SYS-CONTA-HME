<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesController
{
    public function index(): JsonResponse
    {
        $roles = DB::table('roles')->where('codigo', '!=', 'PACIENTE_PORTAL')->orderBy('nombre')->get();
        $sistemas = DB::table('sistemas')->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre']);
        $permisos = DB::table('permisos')->where('activo', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'sistema_id']);
        $sistemasRoles = DB::table('rol_sistema')->join('sistemas', 'sistemas.id', '=', 'rol_sistema.sistema_id')->get(['rol_id', 'codigo'])->groupBy('rol_id');
        $permisosRoles = DB::table('permiso_rol')->join('permisos', 'permisos.id', '=', 'permiso_rol.permiso_id')->get(['rol_id', 'codigo'])->groupBy('rol_id');

        return response()->json([
            'data' => $roles->map(fn (object $rol): array => [
                'id' => $rol->id, 'codigo' => $rol->codigo, 'nombre' => $rol->nombre,
                'descripcion' => $rol->descripcion, 'activo' => (bool) $rol->activo,
                'sistemas' => ($sistemasRoles->get($rol->id) ?? collect())->pluck('codigo')->all(),
                'permisos' => ($permisosRoles->get($rol->id) ?? collect())->pluck('codigo')->all(),
            ]),
            'sistemas' => $sistemas->map(fn (object $sistema): array => [
                'codigo' => $sistema->codigo, 'nombre' => $sistema->nombre,
                'permisos' => $permisos->where('sistema_id', $sistema->id)->values()->map(fn (object $permiso): array => [
                    'codigo' => $permiso->codigo, 'nombre' => $permiso->nombre,
                ])->all(),
            ]),
        ]);
    }

    public function store(GuardarRolRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $id = (string) Str::uuid();
        DB::transaction(function () use ($datos, $id): void {
            DB::table('roles')->insert([
                'id' => $id, 'codigo' => Str::upper(Str::limit(Str::slug($datos['nombre'], '_'), 50, '')).'_'.Str::upper(Str::random(6)),
                'nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null,
                'activo' => $datos['activo'] ?? true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->guardarAccesos($id, $datos);
        });

        return response()->json(['data' => DB::table('roles')->where('id', $id)->first()], 201);
    }

    public function update(GuardarRolRequest $request, string $rol): JsonResponse
    {
        $existente = DB::table('roles')->where('id', $rol)->where('codigo', '!=', 'PACIENTE_PORTAL')->first();
        abort_unless($existente, 404);
        abort_if($existente->codigo === 'ADMINISTRADOR', 403, 'El rol Administrador del sistema está protegido.');
        $datos = $request->validated();
        DB::transaction(function () use ($rol, $datos): void {
            DB::table('roles')->where('id', $rol)->update([
                'nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null,
                'activo' => $datos['activo'] ?? true, 'updated_at' => now(),
            ]);
            $this->guardarAccesos($rol, $datos);
        });

        return response()->json(['data' => DB::table('roles')->where('id', $rol)->first()]);
    }

    private function guardarAccesos(string $rol, array $datos): void
    {
        DB::table('rol_sistema')->where('rol_id', $rol)->delete();
        DB::table('permiso_rol')->where('rol_id', $rol)->delete();
        $sistemas = DB::table('sistemas')->whereIn('codigo', $datos['sistemas'])->pluck('id');
        foreach ($sistemas as $id) {
            DB::table('rol_sistema')->insert(['rol_id' => $rol, 'sistema_id' => $id]);
        }
        foreach (DB::table('permisos')->whereIn('codigo', $datos['permisos'])->whereIn('sistema_id', $sistemas)->pluck('id') as $id) {
            DB::table('permiso_rol')->insert(['rol_id' => $rol, 'permiso_id' => $id]);
        }
    }
}
