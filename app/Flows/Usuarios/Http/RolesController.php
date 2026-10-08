<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesController
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre'=>['required','string','max:100'],'descripcion'=>['nullable','string'],'sistemas'=>['required','array','min:1'],'sistemas.*'=>['string'],'permisos'=>['array'],'permisos.*'=>['string']]);
        $rol = DB::transaction(function () use ($datos) {
            $id = (string) Str::uuid(); $codigo = Str::upper(Str::slug($datos['nombre'], '_'));
            DB::table('roles')->insert(['id'=>$id,'codigo'=>$codigo,'nombre'=>$datos['nombre'],'descripcion'=>$datos['descripcion'] ?? null,'activo'=>true,'created_at'=>now(),'updated_at'=>now()]);
            $sistemas = DB::table('sistemas')->whereIn('codigo',$datos['sistemas'])->pluck('id');
            foreach ($sistemas as $sistemaId) DB::table('rol_sistema')->insert(['rol_id'=>$id,'sistema_id'=>$sistemaId]);
            $permisos = DB::table('permisos')->whereIn('sistema_id',$sistemas)->where(fn ($query) => $query->whereIn('codigo',$datos['permisos'] ?? [])->orWhereIn('nombre',$datos['permisos'] ?? []))->pluck('id');
            foreach ($permisos as $permisoId) DB::table('permiso_rol')->insert(['rol_id'=>$id,'permiso_id'=>$permisoId]);
            return DB::table('roles')->where('id',$id)->first();
        });
        return response()->json(['data'=>$rol], 201);
    }
}
