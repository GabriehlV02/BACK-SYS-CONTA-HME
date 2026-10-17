<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PacientesUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);
        $usuario = UsuarioSistema::factory()->conRol()->create();
        $this->actingAs($usuario, 'contable');

        return $usuario;
    }

    private function datos(): array
    {
        return [
            'nombre' => 'Paciente de prueba', 'ci' => '9382286', 'usuario' => 'paciente.prueba',
            'password' => 'Segura-12345', 'password_confirmation' => 'Segura-12345',
        ];
    }

    public function test_registra_una_cuenta_paciente_con_hash_y_rol_de_portal(): void
    {
        $this->administrador();

        $response = $this->postJson('/api/v1/pacientes-usuarios', $this->datos())
            ->assertCreated()->assertJsonPath('data.nombres', 'Paciente de prueba')
            ->assertJsonPath('data.ci', '9382286')->assertJsonPath('data.estado', 'ACTIVO')
            ->assertJsonMissingPath('data.password');
        $usuario = UsuarioSistema::findOrFail($response->json('data.id'));

        $this->assertTrue(Hash::check('Segura-12345', $usuario->password));
        $this->assertSame('PACIENTE', $usuario->tipo_usuario);
        $this->assertNull($usuario->paciente_id);
        $this->assertDatabaseHas('rol_usuario', [
            'usuario_id' => $usuario->id,
            'rol_id' => DB::table('roles')->where('codigo', 'PACIENTE_PORTAL')->value('id'),
        ]);
        $this->getJson('/api/v1/usuarios')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_lista_solo_cuentas_de_pacientes_y_filtra_nombre_ci_usuario_y_estado(): void
    {
        $this->administrador();
        $id = $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertCreated()->json('data.id');
        UsuarioSistema::factory()->inactivo()->create(['tipo_usuario' => 'PACIENTE', 'nombres' => 'Otro paciente']);

        $this->getJson('/api/v1/pacientes-usuarios')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/v1/pacientes-usuarios?buscar=paciente%20de%20prueba&estado=ACTIVO')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.password');
        $this->getJson('/api/v1/pacientes-usuarios?buscar=9382286')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/pacientes-usuarios?buscar=paciente.prueba')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/pacientes-usuarios?estado=INACTIVO')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_cuenta_paciente_no_puede_iniciar_sesion_en_el_sistema_contable(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertCreated();
        Auth::guard('contable')->logout();

        $this->postJson('/api/login', ['usuario' => 'paciente.prueba', 'password' => 'Segura-12345'])->assertUnauthorized();
        $this->assertGuest('contable');
    }

    public function test_rechaza_ci_duplicado_entre_pacientes(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertCreated();

        $this->postJson('/api/v1/pacientes-usuarios', [...$this->datos(), 'usuario' => 'otro.usuario'])
            ->assertUnprocessable()->assertJsonPath('errors.ci.0', 'Este paciente ya tiene una cuenta registrada con ese CI.');
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'otro.usuario']);
    }

    public function test_rechaza_usuario_duplicado_incluso_si_pertenece_a_un_empleado(): void
    {
        $admin = $this->administrador();

        $this->postJson('/api/v1/pacientes-usuarios', [...$this->datos(), 'usuario' => $admin->usuario])
            ->assertUnprocessable()->assertJsonPath('errors.usuario.0', 'Ese usuario ya está registrado.');
        $this->assertDatabaseCount('usuarios_sistema', 1);
    }

    public function test_exige_los_datos_de_registro(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/pacientes-usuarios', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'ci', 'usuario', 'password'])
            ->assertJsonPath('errors.nombre.0', 'Ingresa el nombre completo del paciente.');
        $this->assertDatabaseCount('usuarios_sistema', 1);
    }

    public function test_rechaza_contrasena_corta_y_confirmacion_diferente(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/pacientes-usuarios', [
            ...$this->datos(), 'password' => 'corta', 'password_confirmation' => 'otra',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.');
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'paciente.prueba']);
    }

    public static function camposDeAdministracion(): array
    {
        return [
            'tipo de usuario' => ['tipo_usuario', 'EMPRESA'],
            'rol libre' => ['rol', 'ADMINISTRADOR'],
            'rol por id' => ['rol_id', '00000000-0000-4000-8000-000000000000'],
            'permisos' => ['permisos', ['USUARIOS_CREAR']],
            'estado' => ['estado', 'ACTIVO'],
            'vinculo clinico no verificado' => ['paciente_id', '00000000-0000-4000-8000-000000000000'],
        ];
    }

    #[DataProvider('camposDeAdministracion')]
    public function test_rechaza_campos_que_podrian_alterar_el_acceso(string $campo, mixed $valor): void
    {
        $this->administrador();

        $this->postJson('/api/v1/pacientes-usuarios', [...$this->datos(), $campo => $valor])
            ->assertUnprocessable()->assertJsonValidationErrors([$campo]);
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'paciente.prueba']);
    }

    public function test_no_crea_cuenta_si_el_rol_de_portal_esta_inactivo(): void
    {
        $this->administrador();
        DB::table('roles')->where('codigo', 'PACIENTE_PORTAL')->update(['activo' => false]);

        $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertUnprocessable()
            ->assertJsonPath('message', 'El rol de acceso de pacientes no está habilitado.');
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'paciente.prueba']);
    }

    public function test_sin_sesion_no_puede_listar_ni_registrar_pacientes(): void
    {
        $this->getJson('/api/v1/pacientes-usuarios')->assertUnauthorized();
        $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertUnauthorized();
    }

    public function test_sin_permisos_no_puede_listar_ni_registrar_pacientes(): void
    {
        $this->administrador();
        $roleId = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');
        $permisos = DB::table('permisos')->whereIn('codigo', ['USUARIOS_VER', 'USUARIOS_CREAR'])->pluck('id');
        DB::table('permiso_rol')->where('rol_id', $roleId)->whereIn('permiso_id', $permisos)->delete();

        $this->getJson('/api/v1/pacientes-usuarios')->assertForbidden();
        $this->postJson('/api/v1/pacientes-usuarios', $this->datos())->assertForbidden();
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'paciente.prueba']);
    }
}
