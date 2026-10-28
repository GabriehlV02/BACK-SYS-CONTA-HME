<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\Caja;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CajasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);
        $usuario = UsuarioSistema::factory()->conRol()->create();
        $this->actingAs($usuario, 'contable');

        return $usuario;
    }

    private function datos(AlmacenInventario $almacen): array
    {
        return ['nombre' => 'Caja Farmacia', 'codigo' => ' caj-001 ', 'sucursalId' => $almacen->sucursal_id,
            'almacenIds' => [$almacen->id], 'tiposItem' => ['Productos']];
    }

    public function test_persiste_caja_con_varios_almacenes_y_usuario_creador(): void
    {
        $usuario = $this->admin();
        $uno = AlmacenInventario::factory()->create(['tipos_item' => ['Productos']]);
        $dos = AlmacenInventario::factory()->create(['sucursal_id' => $uno->sucursal_id, 'tipos_item' => ['Insumos']]);
        $id = $this->postJson('/api/v1/configuracion/cajas', [...$this->datos($uno), 'almacenIds' => [$uno->id, $dos->id], 'tiposItem' => ['Productos', 'Insumos']])
            ->assertCreated()->assertJsonPath('data.codigo', 'CAJ-001')->json('data.id');
        $this->assertDatabaseHas('cajas', ['id' => $id, 'sucursal_id' => $uno->sucursal_id, 'creado_por' => $usuario->id]);
        $this->assertDatabaseHas('almacen_caja', ['caja_id' => $id, 'almacen_id' => $uno->id]);
        $this->assertDatabaseHas('almacen_caja', ['caja_id' => $id, 'almacen_id' => $dos->id]);
        $this->getJson('/api/v1/configuracion/cajas')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.almacenIds');
        $this->postJson('/api/v1/configuracion/cajas', $this->datos($uno))->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertDatabaseCount('cajas', 1);
        $this->assertSame($id, $uno->cajas()->sole()->id);
        $this->assertSame($id, $uno->sucursal->cajas()->sole()->id);
    }

    #[TestWith(['ajeno', 'almacenIds'])]
    #[TestWith(['inactivo', 'almacenIds.0'])]
    #[TestWith(['sucursal_inactiva', 'sucursalId'])]
    #[TestWith(['tipos', 'tiposItem'])]
    #[TestWith(['vacio', 'almacenIds'])]
    #[TestWith(['repetido', 'almacenIds.0'])]
    #[TestWith(['suplantacion', 'creado_por'])]
    public function test_rechaza_relaciones_invalidas_sin_crear_registros(string $caso, string $campo): void
    {
        $usuario = $this->admin();
        $almacen = AlmacenInventario::factory()->create(['tipos_item' => ['Productos']]);
        $datos = $this->datos($almacen);
        if ($caso === 'ajeno') {
            $datos['almacenIds'] = [AlmacenInventario::factory()->create()->id];
        }
        if ($caso === 'inactivo') {
            $almacen->update(['activo' => false]);
        }
        if ($caso === 'sucursal_inactiva') {
            $almacen->sucursal->update(['activo' => false]);
        }
        if ($caso === 'tipos') {
            $datos['tiposItem'] = ['Servicios'];
        }
        if ($caso === 'vacio') {
            $datos['almacenIds'] = [];
        }
        if ($caso === 'repetido') {
            $datos['almacenIds'][] = $almacen->id;
        }
        if ($caso === 'suplantacion') {
            $datos['creado_por'] = $usuario->id;
        }
        $this->postJson('/api/v1/configuracion/cajas', $datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('cajas', 0);
        $this->assertDatabaseCount('almacen_caja', 0);
    }

    public function test_exige_autenticacion_y_permiso_de_gestion(): void
    {
        $this->getJson('/api/v1/configuracion/cajas')->assertUnauthorized();
        $this->postJson('/api/v1/configuracion/cajas', [])->assertUnauthorized();
        $this->admin();
        DB::table('permiso_rol')->where('permiso_id', DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id'))->delete();
        $this->getJson('/api/v1/configuracion/cajas')->assertOk()->assertJsonPath('data', []);
        $this->postJson('/api/v1/configuracion/cajas', [])->assertForbidden();
    }

    public function test_asigna_cajas_reales_al_usuario_y_rechaza_cajas_fuera_de_sus_ubicaciones(): void
    {
        $this->admin();
        $almacen = AlmacenInventario::factory()->create(['tipos_item' => ['Productos']]);
        $caja = Caja::factory()->create(['sucursal_id' => $almacen->sucursal_id]);
        $caja->almacenes()->attach($almacen->id);
        $datos = ['nombres' => 'Cajero', 'usuario' => 'cajero.prueba', 'password' => 'Prueba12345', 'password_confirmation' => 'Prueba12345',
            'rol_id' => DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id'),
            'sucursal_ids' => [$almacen->sucursal_id], 'almacen_ids' => [$almacen->id], 'caja_ids' => [$caja->id]];
        $id = $this->postJson('/api/v1/usuarios', $datos)->assertCreated()->assertJsonPath('data.cajas_asignadas.0.id', $caja->id)->json('data.id');
        $this->assertDatabaseHas('caja_usuario', ['usuario_id' => $id, 'caja_id' => $caja->id]);
        $this->getJson('/api/v1/usuarios?buscar=cajero.prueba')->assertOk()->assertJsonPath('data.0.cajas_asignadas.0.id', $caja->id);
        $this->getJson('/api/v1/usuarios/opciones')->assertOk()->assertJsonPath('data.cajas.0.almacen_ids', [$almacen->id]);
        $this->putJson('/api/v1/usuarios/'.$id, [...$datos, 'tipo_usuario' => 'EMPRESA', 'almacen_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('caja_ids');
        $this->assertDatabaseHas('caja_usuario', ['usuario_id' => $id, 'caja_id' => $caja->id]);
        $this->putJson('/api/v1/usuarios/'.$id, [...$datos, 'tipo_usuario' => 'EMPRESA', 'caja_ids' => []])->assertOk();
        $this->assertDatabaseMissing('caja_usuario', ['usuario_id' => $id]);
        $caja->update(['activo' => false]);
        $this->postJson('/api/v1/usuarios', [...$datos, 'usuario' => 'otro.cajero'])->assertUnprocessable()->assertJsonValidationErrors('caja_ids.0');
    }
}
