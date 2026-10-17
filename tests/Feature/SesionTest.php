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

class SesionTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdministrador(array $attributes = []): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);

        return UsuarioSistema::factory()->conRol()->create([
            'usuario' => 'admin',
            'nombres' => 'Administrador',
            'apellidos' => 'Local',
            ...$attributes,
        ]);
    }

    public function test_inicia_sesion_con_credenciales_validas_y_devuelve_solo_permisos_contables(): void
    {
        $usuario = $this->crearAdministrador(['password' => 'admin']);

        $response = $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'admin', 'rol' => 'OTRO']);

        $response->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.nombre', 'Administrador Local')
            ->assertJsonPath('usuario.rol', 'Administrador del sistema')
            ->assertJsonPath('usuario.permisos', [
                'INVENTARIO_GESTIONAR', 'INVENTARIO_VER', 'MOVIMIENTOS_GESTIONAR',
                'ROLES_GESTIONAR', 'USUARIOS_CREAR', 'USUARIOS_VER', 'VENTAS_REGISTRAR', 'VENTAS_VER',
            ])
            ->assertJsonMissingPath('usuario.password')
            ->assertJsonMissingPath('token')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertAuthenticatedAs($usuario, 'contable');
        $this->assertTrue(Hash::check('admin', $usuario->fresh()->password));
    }

    public static function credencialesInvalidas(): array
    {
        return [
            'contraseña incorrecta' => ['admin', 'incorrecta'],
            'usuario desconocido' => ['desconocido', 'Password-Prueba-2026'],
            'entrada con SQL' => ["admin' OR 1=1 --", 'Password-Prueba-2026'],
        ];
    }

    #[DataProvider('credencialesInvalidas')]
    public function test_devuelve_401_sin_revelar_si_el_usuario_existe(string $usuario, string $password): void
    {
        $this->crearAdministrador();

        $this->postJson('/api/login', compact('usuario', 'password'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Usuario o contraseña incorrectos, o acceso no habilitado.']);
        $this->assertGuest('contable');
    }

    public static function accesosDeshabilitados(): array
    {
        return [
            'usuario inactivo' => ['usuario', 'estado', 'INACTIVO'],
            'paciente' => ['usuario', 'tipo_usuario', 'PACIENTE'],
            'rol inactivo' => ['roles', 'activo', false],
            'sistema inactivo' => ['sistemas', 'activo', false],
        ];
    }

    #[DataProvider('accesosDeshabilitados')]
    public function test_devuelve_401_para_accesos_no_habilitados(string $tabla, string $campo, mixed $valor): void
    {
        $usuario = $this->crearAdministrador();
        if ($tabla === 'usuario') {
            $usuario->update([$campo => $valor]);
        } else {
            DB::table($tabla)->update([$campo => $valor]);
        }

        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'Password-Prueba-2026'])
            ->assertUnauthorized();
        $this->assertGuest('contable');
    }

    public function test_devuelve_401_si_solo_tiene_rol_de_otro_sistema(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        UsuarioSistema::factory()->conRol('PACIENTE_PORTAL')->create(['usuario' => 'paciente']);

        $this->postJson('/api/login', ['usuario' => 'paciente', 'password' => 'Password-Prueba-2026'])
            ->assertUnauthorized();
        $this->assertGuest('contable');
    }

    public function test_devuelve_401_sin_rol_asignado_aunque_el_campo_rol_diga_administrador(): void
    {
        UsuarioSistema::factory()->create(['usuario' => 'admin', 'rol' => 'ADMINISTRADOR']);

        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'Password-Prueba-2026'])
            ->assertUnauthorized();
        $this->assertGuest('contable');
    }

    public function test_devuelve_422_con_mensajes_para_campos_obligatorios(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario', 'password'])
            ->assertJsonPath('errors.usuario.0', 'Ingresa tu usuario.')
            ->assertJsonPath('errors.password.0', 'Ingresa tu contraseña.');
    }

    public static function camposInvalidos(): array
    {
        return [
            'usuario no textual' => [['usuario' => ['admin'], 'password' => 'admin'], 'usuario', 'El usuario debe ser texto.'],
            'usuario largo' => [['usuario' => str_repeat('a', 61), 'password' => 'admin'], 'usuario', 'El usuario no debe superar los 60 caracteres.'],
            'contraseña no textual' => [['usuario' => 'admin', 'password' => ['admin']], 'password', 'La contraseña debe ser texto.'],
            'contraseña larga' => [['usuario' => 'admin', 'password' => str_repeat('a', 256)], 'password', 'La contraseña no debe superar los 255 caracteres.'],
        ];
    }

    #[DataProvider('camposInvalidos')]
    public function test_devuelve_422_para_campos_invalidos(array $payload, string $campo, string $mensaje): void
    {
        $this->postJson('/api/login', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.'.$campo.'.0', $mensaje);
        $this->assertGuest('contable');
    }

    public function test_devuelve_429_tras_cinco_intentos_y_permite_reintentar_al_vencer_el_limite(): void
    {
        $this->freezeTime();
        $this->crearAdministrador();
        for ($intento = 0; $intento < 5; $intento++) {
            $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'incorrecta'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'Password-Prueba-2026'])
            ->assertTooManyRequests()->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.');

        $this->travel(61)->seconds();
        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'Password-Prueba-2026'])->assertOk();
    }

    public function test_recupera_la_sesion_del_usuario_autenticado(): void
    {
        $usuario = $this->crearAdministrador();

        $this->actingAs($usuario, 'contable')->getJson('/api/session')
            ->assertOk()->assertJsonPath('usuario.id', $usuario->id);
    }

    public function test_guarda_en_base_de_datos_la_sesion_de_un_usuario_con_uuid(): void
    {
        config(['session.driver' => 'database']);
        $usuario = $this->crearAdministrador();

        $this->actingAs($usuario, 'contable')->getJson('/api/session')
            ->assertOk()->assertJsonPath('usuario.id', $usuario->id);

        $this->assertDatabaseHas('sessions', ['user_id' => $usuario->id]);
    }

    public function test_revoca_la_sesion_si_el_usuario_es_deshabilitado(): void
    {
        $usuario = $this->crearAdministrador();
        $this->actingAs($usuario, 'contable');
        $usuario->update(['estado' => 'INACTIVO']);

        $this->getJson('/api/session')->assertUnauthorized()
            ->assertJsonPath('message', 'Tu acceso al sistema contable ya no está habilitado.');
        $this->assertGuest('contable');
    }

    public function test_revoca_la_sesion_si_se_retira_el_rol(): void
    {
        $usuario = $this->crearAdministrador();
        $this->actingAs($usuario, 'contable');
        DB::table('rol_usuario')->where('usuario_id', $usuario->id)->delete();

        $this->getJson('/api/session')->assertUnauthorized();
        $this->assertGuest('contable');
    }

    public function test_cierra_la_sesion_y_el_siguiente_acceso_devuelve_401(): void
    {
        $usuario = $this->crearAdministrador();
        $this->actingAs($usuario, 'contable')->withSession(['dato_privado' => 'no conservar']);

        $this->postJson('/api/logout')->assertNoContent()->assertSessionMissing('dato_privado');
        $this->assertGuest('contable');
        Auth::forgetGuards();
        $this->getJson('/api/session')->assertUnauthorized();
    }

    public function test_cerrar_sesion_sin_sesion_activa_es_idempotente(): void
    {
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('contable');
    }

    public static function rutasProtegidas(): array
    {
        return [
            'sesión' => ['GET', '/api/session'],
            'listar clientes' => ['GET', '/api/v1/clientes'],
            'crear cliente' => ['POST', '/api/v1/clientes'],
            'listar ventas' => ['GET', '/api/v1/ventas'],
            'crear venta' => ['POST', '/api/v1/ventas'],
            'listar usuarios' => ['GET', '/api/v1/usuarios'],
            'crear usuario' => ['POST', '/api/v1/usuarios'],
            'crear rol' => ['POST', '/api/v1/roles'],
        ];
    }

    #[DataProvider('rutasProtegidas')]
    public function test_devuelve_401_en_rutas_protegidas_sin_sesion(string $method, string $url): void
    {
        $this->json($method, $url)->assertUnauthorized();
    }

    public function test_devuelve_403_para_modificar_roles_sin_el_permiso(): void
    {
        $usuario = $this->crearAdministrador();
        DB::table('permiso_rol')->where('permiso_id', DB::table('permisos')->where('codigo', 'ROLES_GESTIONAR')->value('id'))->delete();

        $this->actingAs($usuario, 'contable')->postJson('/api/v1/roles', ['nombre' => 'Intruso'])
            ->assertForbidden();
        $this->assertDatabaseMissing('roles', ['nombre' => 'Intruso']);
    }

    public function test_omite_permisos_inactivos_de_la_sesion(): void
    {
        $usuario = $this->crearAdministrador();
        DB::table('permisos')->where('codigo', 'VENTAS_VER')->update(['activo' => false]);

        $response = $this->actingAs($usuario, 'contable')->getJson('/api/session')->assertOk();

        $this->assertNotContains('VENTAS_VER', $response->json('usuario.permisos'));
    }

    public function test_devuelve_419_al_iniciar_sesion_sin_proteccion_csrf(): void
    {
        $this->app->instance('env', 'local');

        $this->postJson('/api/login', ['usuario' => 'admin', 'password' => 'admin'])->assertStatus(419);
        $this->assertGuest('contable');
    }

    public function test_emite_cookie_httponly_y_acepta_el_token_csrf_del_formulario(): void
    {
        $this->crearAdministrador(['password' => 'admin']);
        $this->app->instance('env', 'local');
        $response = $this->getJson('/api/csrf')->assertOk();
        $cookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($cookie->isHttpOnly());

        $this->withHeader('X-CSRF-TOKEN', $response->json('csrf_token'))
            ->postJson('/api/login', ['usuario' => 'admin', 'password' => 'admin'])
            ->assertOk();
        $this->assertAuthenticated('contable');
    }

    public function test_crea_administrador_desde_consola_sin_contrasena_en_texto_plano(): void
    {
        $this->seed(GestionAccesosSeeder::class);

        $this->artisan('usuarios:crear-admin', ['usuario' => 'admin', '--password' => 'admin', '--no-interaction' => true])
            ->expectsOutput('Administrador creado.')->assertSuccessful();

        $usuario = UsuarioSistema::where('usuario', 'admin')->sole();
        $this->assertTrue(Hash::check('admin', $usuario->password));
        $this->assertTrue($usuario->tieneAccesoContable());
    }

    public function test_el_comando_no_sobrescribe_usuarios_existentes(): void
    {
        $usuario = $this->crearAdministrador();

        $this->artisan('usuarios:crear-admin', ['usuario' => 'admin', '--password' => 'admin', '--no-interaction' => true])
            ->assertFailed();

        $this->assertTrue(Hash::check('Password-Prueba-2026', $usuario->fresh()->password));
        $this->assertDatabaseCount('usuarios_sistema', 1);
    }
}
