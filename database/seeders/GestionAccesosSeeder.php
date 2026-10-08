<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GestionAccesosSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach ([['EMPRESA','Trabajador de la empresa'], ['PACIENTE','Paciente con acceso al portal']] as [$codigo, $nombre]) DB::table('tipos_usuarios')->updateOrInsert(['codigo'=>$codigo], ['id'=>(string) Str::uuid(),'nombre'=>$nombre,'activo'=>true,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['USUARIOS_VER','Ver usuarios','usuarios'],['USUARIOS_CREAR','Registrar usuarios','usuarios'],['ROLES_GESTIONAR','Gestionar roles y permisos','usuarios'],['ESTUDIOS_VER_PROPIOS','Consultar estudios propios','estudios']] as [$codigo,$nombre,$modulo]) DB::table('permisos')->updateOrInsert(['codigo'=>$codigo], ['id'=>(string) Str::uuid(),'nombre'=>$nombre,'modulo'=>$modulo,'activo'=>true,'created_at'=>$now,'updated_at'=>$now]);
        foreach ([['ADMINISTRADOR','Administrador del sistema'],['PACIENTE_PORTAL','Paciente del portal']] as [$codigo,$nombre]) DB::table('roles')->updateOrInsert(['codigo'=>$codigo], ['id'=>(string) Str::uuid(),'nombre'=>$nombre,'activo'=>true,'created_at'=>$now,'updated_at'=>$now]);
        $admin = DB::table('roles')->where('codigo','ADMINISTRADOR')->value('id'); $paciente = DB::table('roles')->where('codigo','PACIENTE_PORTAL')->value('id');
        foreach (DB::table('permisos')->pluck('id') as $id) DB::table('permiso_rol')->updateOrInsert(['rol_id'=>$admin,'permiso_id'=>$id]);
        $estudios = DB::table('permisos')->where('codigo','ESTUDIOS_VER_PROPIOS')->value('id'); DB::table('permiso_rol')->updateOrInsert(['rol_id'=>$paciente,'permiso_id'=>$estudios]);
    }
}
