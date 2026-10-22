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
        $sistemas = [['CLINICO', 'Sistema clínico', 'Atención, pacientes, internación y quirófano'], ['CONTABLE', 'Sistema contable', 'Ventas, inventario, movimientos y usuarios'], ['ESTUDIOS', 'Sistema de estudios', 'Imagenología y laboratorio'], ['HEMODIALISIS', 'Sistema de hemodiálisis', 'Atención y seguimiento de diálisis']];
        foreach ($sistemas as [$codigo,$nombre,$descripcion]) {
            DB::table('sistemas')->updateOrInsert(['codigo' => $codigo], ['id' => DB::table('sistemas')->where('codigo', $codigo)->value('id') ?? (string) Str::uuid(), 'nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $permisos = [
            'CLINICO' => [['PACIENTES_VER', 'Ver pacientes', 'pacientes'], ['PACIENTES_REGISTRAR', 'Registrar pacientes', 'pacientes'], ['CONSULTAS_ATENDER', 'Atender consultas', 'consultas'], ['INTERNACION_GESTIONAR', 'Gestionar internación', 'internacion'], ['QUIROFANO_GESTIONAR', 'Gestionar quirófano', 'quirofano'], ['FARMACIA_GESTIONAR', 'Gestionar farmacia', 'farmacia'], ['COCINA_GESTIONAR', 'Gestionar cocina', 'cocina'], ['RECEPCION_VER', 'Ver recepción', 'recepcion'], ['RECEPCION_CAJA_VER', 'Ver caja clínica', 'recepcion'], ['RECEPCION_AGENDA_VER', 'Ver agenda', 'recepcion'], ['RECEPCION_CITAS_GESTIONAR', 'Gestionar citas', 'recepcion'], ['PACIENTES_EDITAR', 'Editar pacientes', 'pacientes'], ['HISTORIAS_CLINICAS_VER', 'Ver historias clínicas', 'pacientes'], ['CONSULTORIO_VER', 'Ver consultorio', 'consultorio'], ['RECETAS_EMITIR', 'Emitir recetas', 'consultorio'], ['INTERNACION_VER', 'Ver internación', 'internacion'], ['SIGNOS_VITALES_REGISTRAR', 'Registrar signos vitales', 'internacion'], ['AUDITORIA_VER', 'Ver auditoría', 'administracion']],
            'CONTABLE' => [['VENTAS_VER', 'Ver ventas', 'ventas'], ['VENTAS_REGISTRAR', 'Registrar ventas', 'ventas'], ['INVENTARIO_VER', 'Ver inventario', 'inventario'], ['INVENTARIO_GESTIONAR', 'Gestionar inventario', 'inventario'], ['MOVIMIENTOS_GESTIONAR', 'Gestionar movimientos', 'movimientos'], ['USUARIOS_VER', 'Ver usuarios', 'usuarios'], ['USUARIOS_CREAR', 'Registrar usuarios', 'usuarios'], ['ROLES_GESTIONAR', 'Gestionar roles y permisos', 'usuarios']],
            'ESTUDIOS' => [['IMAGENOLOGIA_VER', 'Ver imagenología', 'imagenologia'], ['IMAGENOLOGIA_INFORMAR', 'Informar imagenología', 'imagenologia'], ['LABORATORIO_VER', 'Ver laboratorio', 'laboratorio'], ['LABORATORIO_INFORMAR', 'Informar laboratorio', 'laboratorio'], ['ESTUDIOS_PUBLICAR', 'Publicar estudios', 'estudios'], ['ESTUDIOS_VER_PROPIOS', 'Consultar estudios propios', 'estudios']],
            'HEMODIALISIS' => [['HEMODIALISIS_VER', 'Ver hemodiálisis', 'hemodialisis'], ['HEMODIALISIS_REGISTRAR', 'Registrar sesión', 'hemodialisis'], ['HEMODIALISIS_SEGUIMIENTO', 'Ver seguimiento', 'hemodialisis'], ['HEMODIALISIS_INFORMAR', 'Registrar informe', 'hemodialisis']],
        ];
        foreach ($permisos as $codigoSistema => $items) {
            $sistemaId = DB::table('sistemas')->where('codigo', $codigoSistema)->value('id');
            foreach ($items as [$codigo,$nombre,$modulo]) {
                DB::table('permisos')->updateOrInsert(['codigo' => $codigo], ['id' => DB::table('permisos')->where('codigo', $codigo)->value('id') ?? (string) Str::uuid(), 'nombre' => $nombre, 'modulo' => $modulo, 'sistema_id' => $sistemaId, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach ([['ADMINISTRADOR', 'Administrador del sistema'], ['PACIENTE_PORTAL', 'Paciente del portal']] as [$codigo,$nombre]) {
            DB::table('roles')->updateOrInsert(['codigo' => $codigo], ['id' => DB::table('roles')->where('codigo', $codigo)->value('id') ?? (string) Str::uuid(), 'nombre' => $nombre, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $admin = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');
        $paciente = DB::table('roles')->where('codigo', 'PACIENTE_PORTAL')->value('id');
        foreach (DB::table('sistemas')->pluck('id') as $id) {
            DB::table('rol_sistema')->updateOrInsert(['rol_id' => $admin, 'sistema_id' => $id]);
        }
        foreach (DB::table('permisos')->pluck('id') as $id) {
            DB::table('permiso_rol')->updateOrInsert(['rol_id' => $admin, 'permiso_id' => $id]);
        }
        $estudios = DB::table('permisos')->where('codigo', 'ESTUDIOS_VER_PROPIOS')->value('id');
        DB::table('permiso_rol')->updateOrInsert(['rol_id' => $paciente, 'permiso_id' => $estudios]);
    }
}
