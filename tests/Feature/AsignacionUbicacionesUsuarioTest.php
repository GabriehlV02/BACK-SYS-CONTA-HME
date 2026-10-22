<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\SucursalInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsignacionUbicacionesUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');

        return ['nombres' => 'Usuario asignado', 'usuario' => 'ubicaciones', 'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123', 'rol_id' => DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id')];
    }

    public function test_registra_varias_sucursales_y_almacenes_y_los_devuelve_al_listar(): void
    {
        $datos = $this->datos();
        $a = AlmacenInventario::factory()->create();
        $b = AlmacenInventario::factory()->create();
        $id = $this->postJson('/api/v1/usuarios', [...$datos, 'sucursal_ids' => [$a->sucursal_id, $b->sucursal_id], 'almacen_ids' => [$a->id, $b->id]])
            ->assertCreated()->assertJsonCount(2, 'data.sucursales_asignadas')->assertJsonCount(2, 'data.almacenes_asignados')->json('data.id');
        $this->assertDatabaseHas('sucursal_usuario', ['usuario_id' => $id, 'sucursal_id' => $a->sucursal_id]);
        $this->assertDatabaseHas('almacen_usuario', ['usuario_id' => $id, 'almacen_id' => $b->id]);
        $this->getJson('/api/v1/usuarios?buscar=ubicaciones')->assertOk()->assertJsonCount(2, 'data.0.sucursales_asignadas')->assertJsonCount(2, 'data.0.almacenes_asignados');
    }

    public function test_actualiza_y_quita_asignaciones_sin_duplicarlas(): void
    {
        $datos = $this->datos();
        $a = AlmacenInventario::factory()->create();
        $b = AlmacenInventario::factory()->create();
        $usuario = UsuarioSistema::factory()->create();
        $this->putJson('/api/v1/usuarios/'.$usuario->id, [...$datos, 'sucursal_ids' => [$a->sucursal_id, $b->sucursal_id], 'almacen_ids' => [$a->id, $b->id]])->assertOk();
        $this->putJson('/api/v1/usuarios/'.$usuario->id, [...$datos, 'sucursal_ids' => [$a->sucursal_id], 'almacen_ids' => [$a->id]])->assertOk();
        $this->assertDatabaseCount('sucursal_usuario', 1);
        $this->assertDatabaseCount('almacen_usuario', 1);
        $this->putJson('/api/v1/usuarios/'.$usuario->id, [...$datos, 'sucursal_ids' => [], 'almacen_ids' => []])->assertOk();
        $this->assertDatabaseCount('sucursal_usuario', 0);
        $this->assertDatabaseCount('almacen_usuario', 0);
    }

    public function test_rechaza_almacen_ajeno_a_sucursal_e_inexistentes_inactivos_o_repetidos(): void
    {
        $datos = $this->datos();
        $a = AlmacenInventario::factory()->create();
        $otra = SucursalInventario::factory()->create();
        $base = [...$datos, 'sucursal_ids' => [$otra->id], 'almacen_ids' => [$a->id]];
        $this->postJson('/api/v1/usuarios', $base)->assertUnprocessable()->assertJsonValidationErrors('almacen_ids');
        $this->postJson('/api/v1/usuarios', [...$base, 'sucursal_ids' => [$a->sucursal_id], 'almacen_ids' => [$a->id, $a->id]])->assertUnprocessable();
        $this->postJson('/api/v1/usuarios', [...$base, 'sucursal_ids' => [999999]])->assertUnprocessable();
        $a->update(['activo' => false]);
        $this->postJson('/api/v1/usuarios', [...$base, 'sucursal_ids' => [$a->sucursal_id]])->assertUnprocessable()->assertJsonValidationErrors('almacen_ids.0');
        $this->assertDatabaseMissing('usuarios_sistema', ['usuario' => 'ubicaciones']);
    }

    public function test_opciones_solo_incluyen_catalogos_activos_y_exigen_sesion(): void
    {
        $this->getJson('/api/v1/usuarios/opciones')->assertUnauthorized();
        $this->datos();
        $a = AlmacenInventario::factory()->create();
        AlmacenInventario::factory()->create(['activo' => false]);
        AlmacenInventario::factory()->create(['sucursal_id' => SucursalInventario::factory()->create(['activo' => false])->id]);
        $this->getJson('/api/v1/usuarios/opciones')->assertOk()->assertJsonCount(1, 'data.almacenes')->assertJsonPath('data.almacenes.0.id', $a->id);
    }
}
