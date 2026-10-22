<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Dispositivo;
use App\Models\SucursalInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ConfiguracionGeneralTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);
        $usuario = UsuarioSistema::factory()->conRol()->create();
        $this->actingAs($usuario, 'contable');

        return $usuario;
    }

    public function test_exige_autenticacion_y_permisos_para_listar_y_registrar(): void
    {
        $this->getJson('/api/v1/configuracion/general')->assertUnauthorized();
        foreach (['sucursales', 'almacenes', 'dispositivos'] as $recurso) {
            $this->postJson('/api/v1/configuracion/'.$recurso, [])->assertUnauthorized();
        }
        $this->administrador();
        $permisos = DB::table('permisos')->whereIn('codigo', ['INVENTARIO_VER', 'INVENTARIO_GESTIONAR'])->pluck('id');
        DB::table('permiso_rol')->whereIn('permiso_id', $permisos)->delete();
        $this->getJson('/api/v1/configuracion/general')->assertForbidden();
        foreach (['sucursales', 'almacenes', 'dispositivos'] as $recurso) {
            $this->postJson('/api/v1/configuracion/'.$recurso, [])->assertForbidden();
        }
        $this->assertDatabaseCount('dispositivos', 0);
    }

    public function test_permiso_de_consulta_no_permite_registrar_configuracion(): void
    {
        $this->administrador();
        DB::table('permiso_rol')->where('permiso_id', DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id'))->delete();
        $this->getJson('/api/v1/configuracion/general')->assertOk();
        $this->postJson('/api/v1/configuracion/sucursales', ['nombre' => 'Sucursal', 'direccion' => 'Dirección'])->assertForbidden();
    }

    public function test_lista_datos_reales_vacios_sin_registros_de_ejemplo(): void
    {
        $this->administrador();
        $this->getJson('/api/v1/configuracion/general')->assertOk()->assertExactJson(['data' => ['sucursales' => [], 'almacenes' => [], 'dispositivos' => []]]);
    }

    public function test_registra_sucursal_y_almacen_en_el_mismo_catalogo_del_inventario(): void
    {
        $this->administrador();
        $sucursal = $this->postJson('/api/v1/configuracion/sucursales', ['nombre' => 'Hospital', 'direccion' => 'Avenida principal 25'])
            ->assertCreated()->assertJsonPath('data.direccion', 'Avenida principal 25')->json('data.id');
        $almacen = $this->postJson('/api/v1/configuracion/almacenes', ['nombre' => 'Farmacia', 'codigo' => ' alm-far ', 'sucursalId' => $sucursal, 'tiposItem' => ['Productos', 'Insumos']])
            ->assertCreated()->assertJsonPath('data.codigo', 'ALM-FAR')->assertJsonPath('data.tiposItem', ['Productos', 'Insumos'])->json('data.id');
        $this->assertDatabaseHas('sucursales_inventario', ['id' => $sucursal, 'direccion' => 'Avenida principal 25']);
        $this->assertDatabaseHas('almacenes_inventario', ['id' => $almacen, 'sucursal_id' => $sucursal]);
        $this->getJson('/api/v1/configuracion/general')->assertOk()->assertJsonPath('data.sucursales.0.almacenes', 1)->assertJsonPath('data.almacenes.0.id', $almacen);
        $this->getJson('/api/v1/adquisiciones/opciones')->assertOk()->assertJsonPath('data.almacenes.0.id', $almacen);
        $this->postJson('/api/v1/configuracion/almacenes', ['nombre' => 'Duplicado', 'codigo' => 'alm-far', 'sucursalId' => $sucursal, 'tiposItem' => ['Productos']])
            ->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertDatabaseCount('almacenes_inventario', 1);
    }

    #[TestWith([[]])]
    #[TestWith([['Medicamentos']])]
    #[TestWith([['Productos', 'Productos']])]
    public function test_rechaza_tipos_de_almacen_vacios_desconocidos_o_repetidos(array $tipos): void
    {
        $this->administrador();
        $sucursal = SucursalInventario::factory()->create();
        $this->postJson('/api/v1/configuracion/almacenes', ['nombre' => 'Farmacia', 'codigo' => 'ALM-FAR', 'sucursalId' => $sucursal->id, 'tiposItem' => $tipos])->assertUnprocessable();
        $this->assertDatabaseCount('almacenes_inventario', 0);
    }

    public function test_registra_dispositivo_y_vincula_al_usuario_autenticado(): void
    {
        $usuario = $this->administrador();
        $sucursal = SucursalInventario::factory()->create();
        $datos = ['nombre' => 'Equipo de caja', 'codigo' => ' dis-01 ', 'tipo' => 'Computadora', 'sucursalId' => $sucursal->id, 'uso' => 'Institucional'];
        $id = $this->postJson('/api/v1/configuracion/dispositivos', $datos)->assertCreated()->assertJsonPath('data.codigo', 'DIS-01')->assertJsonPath('data.area', 'Sin área asignada')->json('data.id');
        $this->assertDatabaseHas('dispositivos', ['id' => $id, 'usuario_id' => $usuario->id, 'sucursal_id' => $sucursal->id]);
        $this->postJson('/api/v1/configuracion/dispositivos', $datos)->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->getJson('/api/v1/configuracion/general')->assertOk()->assertJsonPath('data.dispositivos.0.id', $id)->assertJsonMissingPath('data.dispositivos.0.usuario.password');
        $this->assertDatabaseCount('dispositivos', 1);
    }

    public function test_no_permite_suplantar_al_usuario_vinculado_al_dispositivo(): void
    {
        $this->administrador();
        $sucursal = SucursalInventario::factory()->create();
        $otro = UsuarioSistema::factory()->create();
        $this->postJson('/api/v1/configuracion/dispositivos', ['nombre' => 'Equipo', 'codigo' => 'DIS-1', 'tipo' => 'Computadora', 'sucursalId' => $sucursal->id, 'uso' => 'Personal', 'usuario_id' => $otro->id])->assertUnprocessable()->assertJsonValidationErrors('usuario_id');
        $this->assertDatabaseCount('dispositivos', 0);
    }

    public function test_no_registra_almacenes_ni_dispositivos_en_sucursal_inactiva_o_inexistente(): void
    {
        $this->administrador();
        $sucursal = SucursalInventario::factory()->create(['activo' => false]);
        foreach ([$sucursal->id, $sucursal->id + 100] as $id) {
            $this->postJson('/api/v1/configuracion/almacenes', ['nombre' => 'Almacén', 'codigo' => 'ALM-1', 'sucursalId' => $id, 'tiposItem' => ['Insumos']])->assertUnprocessable()->assertJsonValidationErrors('sucursalId');
            $this->postJson('/api/v1/configuracion/dispositivos', ['nombre' => 'Equipo', 'codigo' => 'DIS-1', 'tipo' => 'Computadora', 'sucursalId' => $id, 'uso' => 'Personal'])->assertUnprocessable()->assertJsonValidationErrors('sucursalId');
        }
        $this->assertDatabaseCount('almacenes_inventario', 0);
        $this->assertDatabaseCount('dispositivos', 0);
    }

    public function test_valida_campos_requeridos_y_no_filtra_contrasenas_al_listar_dispositivos(): void
    {
        $usuario = $this->administrador();
        $this->postJson('/api/v1/configuracion/sucursales', [])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'direccion']);
        $this->postJson('/api/v1/configuracion/dispositivos', [])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'codigo', 'tipo', 'sucursalId', 'uso']);
        Dispositivo::factory()->create(['usuario_id' => $usuario->id]);
        $datos = $this->getJson('/api/v1/configuracion/general')->assertOk()->json('data.dispositivos.0');
        $this->assertEqualsCanonicalizing(['id', 'nombre', 'codigo', 'tipo', 'sucursalId', 'area', 'uso', 'vinculadoA', 'estado'], array_keys($datos));
    }
}
