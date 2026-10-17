<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);
        $usuario = UsuarioSistema::factory()->conRol()->create();
        $this->actingAs($usuario, 'contable');

        return $usuario;
    }

    private function crearRol(array $datos = []): string
    {
        return $this->postJson('/api/v1/roles', [
            'nombre' => 'Operador', 'descripcion' => 'Ventas de mostrador',
            'sistemas' => ['CONTABLE'], 'permisos' => ['VENTAS_VER'], ...$datos,
        ])->assertCreated()->json('data.id');
    }

    private function datosUsuario(string $rol): array
    {
        return [
            'nombres' => 'Ana', 'apellidos' => 'Perez', 'ci' => '12345',
            'usuario' => 'ana', 'password' => 'Segura-12345', 'password_confirmation' => 'Segura-12345',
            'rol_id' => $rol, 'sucursal' => 'Casa matriz', 'cajas' => ['Caja 01', 'Caja 02'],
            'almacenes' => ['Almacén principal'],
        ];
    }

    public function test_lista_roles_y_catalogo_de_permisos_reales_sin_incluir_el_rol_de_pacientes(): void
    {
        $this->administrador();

        $response = $this->getJson('/api/v1/roles')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'ADMINISTRADOR')->assertJsonCount(4, 'sistemas');

        $this->assertContains('VENTAS_VER', $response->json('data.0.permisos'));
    }

    public function test_crea_rol_usuario_y_permite_login_con_solo_los_permisos_asignados(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $response = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))
            ->assertCreated()->assertJsonMissingPath('data.password');
        $usuario = UsuarioSistema::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('Segura-12345', $usuario->password));
        $this->assertDatabaseHas('rol_usuario', ['usuario_id' => $usuario->id, 'rol_id' => $rol]);
        $this->assertSame(['Caja 01', 'Caja 02'], $usuario->cajas);

        Auth::guard('contable')->logout();
        $this->postJson('/api/login', ['usuario' => 'ana', 'password' => 'Segura-12345'])
            ->assertOk()->assertJsonPath('usuario.rol', 'Operador')->assertJsonPath('usuario.permisos', ['VENTAS_VER']);
        $this->getJson('/api/v1/ventas')->assertOk();
        $this->getJson('/api/v1/usuarios')->assertForbidden();
        $this->postJson('/api/v1/ventas', [])->assertForbidden();
        $this->postJson('/api/v1/roles', [])->assertForbidden();
    }

    public function test_actualiza_permisos_del_rol_y_la_sesion_refleja_los_cambios(): void
    {
        $admin = $this->administrador();
        $rol = $this->crearRol();
        $usuario = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated()->json('data.id');

        $this->putJson('/api/v1/roles/'.$rol, [
            'nombre' => 'Operador', 'sistemas' => ['CONTABLE'], 'permisos' => ['INVENTARIO_VER'],
        ])->assertOk();
        $this->actingAs(UsuarioSistema::findOrFail($usuario), 'contable')->getJson('/api/session')
            ->assertOk()->assertJsonPath('usuario.permisos', ['INVENTARIO_VER']);
        $this->getJson('/api/v1/ventas')->assertForbidden();
    }

    public function test_rechaza_permisos_de_sistemas_no_seleccionados(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/roles', [
            'nombre' => 'Inconsistente', 'sistemas' => ['CONTABLE'], 'permisos' => ['PACIENTES_VER'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permisos']);
        $this->assertDatabaseMissing('roles', ['nombre' => 'Inconsistente']);
    }

    public function test_rechaza_codigos_de_permisos_o_sistemas_inexistentes(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/roles', [
            'nombre' => 'Inválido', 'sistemas' => ['NO_EXISTE'], 'permisos' => ['NO_EXISTE'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['sistemas.0', 'permisos.0']);
    }

    public function test_no_permite_modificar_el_rol_administrador_inicial(): void
    {
        $this->administrador();
        $rol = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');

        $this->putJson('/api/v1/roles/'.$rol, [
            'nombre' => 'Administrador del sistema', 'sistemas' => ['CONTABLE'], 'permisos' => [],
        ])->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $rol, 'activo' => true]);
    }

    public function test_devuelve_422_para_usuario_duplicado_y_contrasena_sin_confirmar(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated();

        $this->postJson('/api/v1/usuarios', [
            ...$this->datosUsuario($rol), 'password_confirmation' => 'diferente',
        ])->assertUnprocessable()->assertJsonValidationErrors(['usuario', 'password']);
        $this->assertDatabaseCount('usuarios_sistema', 2);
    }

    public function test_edita_asignaciones_y_conserva_el_hash_si_no_se_cambia_la_contrasena(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $id = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated()->json('data.id');
        $hash = UsuarioSistema::findOrFail($id)->password;

        $this->putJson('/api/v1/usuarios/'.$id, [
            ...$this->datosUsuario($rol), 'nombres' => 'Ana Maria', 'password' => '', 'password_confirmation' => '',
            'cajas' => ['Caja 03'], 'estado' => 'INACTIVO',
        ])->assertOk()->assertJsonPath('data.nombres', 'Ana Maria')->assertJsonPath('data.cajas', ['Caja 03'])
            ->assertJsonMissingPath('data.password');
        $this->assertSame($hash, UsuarioSistema::findOrFail($id)->password);
        Auth::guard('contable')->logout();
        $this->postJson('/api/login', ['usuario' => 'ana', 'password' => 'Segura-12345'])->assertUnauthorized();
    }

    public function test_puede_cambiar_la_contrasena_del_usuario(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $id = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated()->json('data.id');

        $this->putJson('/api/v1/usuarios/'.$id, [
            ...$this->datosUsuario($rol), 'password' => 'NuevaClave-123', 'password_confirmation' => 'NuevaClave-123',
        ])->assertOk();
        $this->assertTrue(Hash::check('NuevaClave-123', UsuarioSistema::findOrFail($id)->password));
    }

    public function test_lista_filtra_y_pagina_usuarios_del_sistema_sin_mezclar_pacientes(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated();
        UsuarioSistema::factory()->create(['tipo_usuario' => 'PACIENTE', 'nombres' => 'Ana paciente']);

        $this->getJson('/api/v1/usuarios?buscar=Operador&estado=ACTIVO&por_pagina=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.usuario', 'ana')
            ->assertJsonPath('data.0.roles.0.id', $rol)->assertJsonMissingPath('data.0.password');
    }

    public function test_no_permite_editar_pacientes_desde_la_ruta_de_usuarios_del_sistema(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $paciente = UsuarioSistema::factory()->create(['tipo_usuario' => 'PACIENTE']);

        $this->putJson('/api/v1/usuarios/'.$paciente->id, $this->datosUsuario($rol))->assertNotFound();
        $this->assertSame('PACIENTE', $paciente->fresh()->tipo_usuario);
    }

    public function test_no_permite_desactivar_o_quitar_rol_al_administrador_inicial(): void
    {
        $admin = $this->administrador();
        $rol = $this->crearRol();

        $this->putJson('/api/v1/usuarios/'.$admin->id, [
            ...$this->datosUsuario($rol), 'usuario' => $admin->usuario, 'estado' => 'INACTIVO',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rol_id']);
        $this->assertSame('ACTIVO', $admin->fresh()->estado);
    }

    public function test_un_registrador_no_puede_asignar_ni_crear_roles_con_permisos_superiores(): void
    {
        $this->administrador();
        $rol = $this->crearRol(['permisos' => ['USUARIOS_VER', 'USUARIOS_CREAR', 'ROLES_GESTIONAR']]);
        $id = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated()->json('data.id');
        $adminId = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');
        $this->actingAs(UsuarioSistema::findOrFail($id), 'contable');

        $this->postJson('/api/v1/usuarios', [
            ...$this->datosUsuario($adminId), 'usuario' => 'escalado',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rol_id']);
        $this->postJson('/api/v1/roles', [
            'nombre' => 'Escalado', 'sistemas' => ['CONTABLE'], 'permisos' => ['VENTAS_REGISTRAR'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['permisos']);
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'escalado']);
    }

    public function test_sin_sesion_no_puede_listar_roles_ni_actualizar_usuarios(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
        $this->putJson('/api/v1/usuarios/00000000-0000-4000-8000-000000000000', [])->assertUnauthorized();
    }

    public function test_un_rol_sin_acceso_contable_no_permite_entrar_a_este_sistema(): void
    {
        $this->administrador();
        $rol = $this->crearRol(['sistemas' => ['CLINICO'], 'permisos' => ['PACIENTES_VER']]);
        $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated();
        Auth::guard('contable')->logout();

        $this->postJson('/api/login', ['usuario' => 'ana', 'password' => 'Segura-12345'])->assertUnauthorized();
    }

    public function test_desactivar_rol_revoca_sesion_del_usuario(): void
    {
        $this->administrador();
        $rol = $this->crearRol();
        $id = $this->postJson('/api/v1/usuarios', $this->datosUsuario($rol))->assertCreated()->json('data.id');

        $this->putJson('/api/v1/roles/'.$rol, [
            'nombre' => 'Operador', 'sistemas' => ['CONTABLE'], 'permisos' => ['VENTAS_VER'], 'activo' => false,
        ])->assertOk();
        $this->actingAs(UsuarioSistema::findOrFail($id), 'contable')->getJson('/api/session')->assertUnauthorized();
    }
}
