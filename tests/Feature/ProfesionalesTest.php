<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Profesional;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfesionalesTest extends TestCase
{
    use RefreshDatabase;

    private function administrar(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }

    private function datos(): array
    {
        return ['usuario_id' => UsuarioSistema::factory()->create(['ci' => 'TEST-123'])->id, 'tipo' => 'Médico/a',
            'especialidades' => ['Medicina general', 'Cirugía general'], 'rol' => 'Cirujano', 'matricula' => 'MED-TEST-123', 'telefono' => '70000000'];
    }

    public function test_asigna_usuario_existente_sin_modificar_su_acceso(): void
    {
        $this->administrar();
        $datos = $this->datos();
        $usuario = UsuarioSistema::findOrFail($datos['usuario_id']);
        $acceso = $usuario->getRawOriginal();
        $id = $this->postJson('/api/v1/inventario/profesionales', [...$datos, 'nombres' => 'Nombre falso', 'ci' => 'CI falso'])->assertCreated()
            ->assertJsonPath('data.nombres', $usuario->nombres)
            ->assertJsonPath('data.activo', true)->assertJsonPath('data.especialidades.1', 'Cirugía general')->json('data.id');
        $this->getJson('/api/v1/inventario/profesionales')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->assertDatabaseHas('profesionales', ['id' => $id, 'usuario_id' => $usuario->id, 'ci' => 'TEST-123']);
        $this->assertDatabaseCount('usuarios_sistema', 2);
        $this->assertSame($acceso, $usuario->fresh()->getRawOriginal());
    }

    public function test_rechaza_duplicados_y_actualiza_su_propio_registro(): void
    {
        $this->administrar();
        $datos = $this->datos();
        $id = $this->postJson('/api/v1/inventario/profesionales', $datos)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/inventario/profesionales', $datos)->assertUnprocessable()->assertJsonValidationErrors(['usuario_id', 'matricula']);
        $this->putJson('/api/v1/inventario/profesionales/'.$id, [...$datos, 'activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->getJson('/api/v1/inventario/profesionales?activo=1')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('profesionales', 1);
    }

    public function test_no_vincula_cuentas_de_pacientes_ni_repite_una_cuenta(): void
    {
        $this->administrar();
        $paciente = UsuarioSistema::factory()->create(['tipo_usuario' => 'PACIENTE']);
        $this->postJson('/api/v1/inventario/profesionales', [...$this->datos(), 'usuario_id' => $paciente->id])->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $usuario = UsuarioSistema::factory()->create();
        Profesional::factory()->create(['usuario_id' => $usuario->id]);
        $this->postJson('/api/v1/inventario/profesionales', [...$this->datos(), 'usuario_id' => $usuario->id])->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $this->assertDatabaseCount('profesionales', 1);
    }

    public function test_especialidad_matricula_y_ci_son_opcionales_para_enfermeria(): void
    {
        $this->administrar();
        foreach (range(1, 2) as $numero) {
            $usuario = UsuarioSistema::factory()->create(['ci' => null]);
            $this->postJson('/api/v1/inventario/profesionales', ['usuario_id' => $usuario->id, 'tipo' => 'Enfermero/a', 'especialidades' => []])
                ->assertCreated()->assertJsonPath('data.especialidades', [])->assertJsonPath('data.ci', null)->assertJsonPath('data.matricula', null);
        }
        $this->assertDatabaseCount('profesionales', 2);
    }

    public function test_exige_usuario_y_no_permite_crear_cuentas_ni_asignar_permisos(): void
    {
        $this->administrar();
        $this->postJson('/api/v1/inventario/profesionales', ['tipo' => 'Administrador'])->assertUnprocessable()->assertJsonValidationErrors(['usuario_id', 'tipo']);
        $this->postJson('/api/v1/inventario/profesionales', [...$this->datos(), 'cuenta' => ['usuario' => 'nuevo'], 'password' => 'secreto', 'rol_sistema_id' => 'otro', 'permisos' => ['ADMINISTRAR_CATALOGO']])
            ->assertUnprocessable()->assertJsonValidationErrors(['cuenta', 'password', 'rol_sistema_id', 'permisos']);
        $this->assertDatabaseCount('profesionales', 0);
        $this->assertDatabaseCount('usuarios_sistema', 2);
    }

    public function test_sesion_y_permisos_protegen_el_catalogo(): void
    {
        $this->getJson('/api/v1/inventario/profesionales')->assertUnauthorized();
        $this->postJson('/api/v1/inventario/profesionales', $this->datos())->assertUnauthorized();
        $this->administrar();
        DB::table('permiso_rol')->delete();
        $this->postJson('/api/v1/inventario/profesionales', $this->datos())->assertForbidden();
        $this->assertDatabaseCount('profesionales', 0);
    }
}
