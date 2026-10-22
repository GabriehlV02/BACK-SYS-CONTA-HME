<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class LimpiezaEsquemaAutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS_RETIRADAS = ['users', 'password_reset_tokens', 'usuario_sistema', 'rol_usuario_sistema', 'permiso_usuario_sistema'];

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_10_21_131303_remove_unused_authentication_tables.php');
    }

    public function test_login_y_permisos_funcionan_sin_las_tablas_antiguas(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->assertFalse(Schema::hasTable('tipos_usuarios'));
        $this->assertFalse(Schema::hasColumn('usuarios_sistema', 'tipo_usuario_id'));
        $usuario = UsuarioSistema::factory()->conRol()->create(['usuario' => 'admin.limpieza', 'password' => 'admin']);
        foreach (self::TABLAS_RETIRADAS as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla));
        }
        $this->postJson('/api/login', ['usuario' => 'admin.limpieza', 'password' => 'admin'])->assertOk()->assertJsonPath('usuario.id', $usuario->id);
        $this->getJson('/api/session')->assertOk()->assertJsonPath('usuario.id', $usuario->id);
        $this->getJson('/api/v1/roles')->assertOk();
        $this->getJson('/api/v1/usuarios')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->getJson('/api/session')->assertUnauthorized();
    }

    public function test_retira_tipo_duplicado_sin_cambiar_clasificacion_de_usuarios(): void
    {
        $usuario = UsuarioSistema::factory()->create(['tipo_usuario' => 'EMPRESA']);
        $migracion = require database_path('migrations/2026_10_21_205024_remove_unused_user_types.php');
        $migracion->down();
        DB::table('usuarios_sistema')->where('id', $usuario->id)->update(['tipo_usuario_id' => DB::table('tipos_usuarios')->where('codigo', 'EMPRESA')->value('id')]);
        $migracion->up();
        $this->assertDatabaseHas('usuarios_sistema', ['id' => $usuario->id, 'tipo_usuario' => 'EMPRESA']);
        $this->assertFalse(Schema::hasTable('tipos_usuarios'));
    }

    public function test_no_retira_tipos_de_usuario_si_hay_clasificaciones_contradictorias(): void
    {
        $usuario = UsuarioSistema::factory()->create(['tipo_usuario' => 'EMPRESA']);
        $migracion = require database_path('migrations/2026_10_21_205024_remove_unused_user_types.php');
        $migracion->down();
        DB::table('usuarios_sistema')->where('id', $usuario->id)->update(['tipo_usuario_id' => DB::table('tipos_usuarios')->where('codigo', 'PACIENTE')->value('id')]);
        try {
            $migracion->up();
            $this->fail('Debe conservar las clasificaciones contradictorias para revisarlas.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('inconsistentes', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('tipos_usuarios'));
        $this->assertDatabaseHas('usuarios_sistema', ['id' => $usuario->id, 'tipo_usuario' => 'EMPRESA']);
    }

    public function test_reversion_recrea_estructuras_y_limpieza_no_afecta_a_usuarios_reales(): void
    {
        $usuario = UsuarioSistema::factory()->create();
        $migracion = $this->migracion();
        $migracion->down();
        foreach (self::TABLAS_RETIRADAS as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla));
        }
        $migracion->up();
        foreach (self::TABLAS_RETIRADAS as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla));
        }
        $this->assertDatabaseHas('usuarios_sistema', ['id' => $usuario->id]);
    }

    #[TestWith(['users'])]
    #[TestWith(['password_reset_tokens'])]
    #[TestWith(['usuario_sistema'])]
    #[TestWith(['rol_usuario_sistema'])]
    #[TestWith(['permiso_usuario_sistema'])]
    public function test_no_elimina_ninguna_tabla_si_hay_datos_pendientes_de_revision(string $tabla): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        if ($tabla === 'users') {
            DB::table($tabla)->insert(['name' => 'Usuario histórico', 'email' => 'historico@example.test', 'password' => 'hash-historico']);
        } elseif ($tabla === 'password_reset_tokens') {
            DB::table($tabla)->insert(['email' => 'historico@example.test', 'token' => 'token-historico']);
        } else {
            $this->seed(GestionAccesosSeeder::class);
            $usuario = UsuarioSistema::factory()->create();
            $accesoId = (string) Str::uuid();
            DB::table('usuario_sistema')->insert(['id' => $accesoId, 'usuario_id' => $usuario->id, 'sistema_id' => DB::table('sistemas')->where('codigo', 'CONTABLE')->value('id')]);
            if ($tabla === 'rol_usuario_sistema') {
                DB::table($tabla)->insert(['usuario_sistema_id' => $accesoId, 'rol_id' => DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id')]);
            } elseif ($tabla === 'permiso_usuario_sistema') {
                DB::table($tabla)->insert(['usuario_sistema_id' => $accesoId, 'permiso_id' => DB::table('permisos')->where('codigo', 'VENTAS_VER')->value('id')]);
            }
        }
        try {
            $migracion->up();
            $this->fail('La migración debe detenerse cuando encuentra datos históricos.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("No se elimina {$tabla}", $exception->getMessage());
        }
        foreach (self::TABLAS_RETIRADAS as $retirada) {
            $this->assertTrue(Schema::hasTable($retirada));
        }
        $this->assertDatabaseCount($tabla, 1);
    }
}
