<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\Caja;
use App\Models\ExistenciaLote;
use App\Models\IngresoStock;
use App\Models\LoteItem;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AislamientoAlmacenesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cajas_solo_expone_almacenes_autorizados_y_no_permite_registrar_al_operador(): void
    {
        $compartida = Caja::factory()->create(['sucursal_id' => $this->propio->sucursal_id]);
        $compartida->almacenes()->attach([$this->propio->id, $this->ajeno->id]);
        $ajena = Caja::factory()->create(['sucursal_id' => $this->ajeno->sucursal_id]);
        $ajena->almacenes()->attach($this->ajeno->id);
        $this->getJson('/api/v1/configuracion/cajas')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $compartida->id)->assertJsonPath('data.0.almacenIds', [$this->propio->id]);
        $this->postJson('/api/v1/configuracion/cajas', [])->assertForbidden();
        $this->getJson('/api/v1/usuarios/opciones')->assertOk()->assertJsonCount(1, 'data.cajas')
            ->assertJsonPath('data.cajas.0.id', $compartida->id)->assertJsonPath('data.cajas.0.almacen_ids', [$this->propio->id]);
        $this->usuario->almacenesAsignados()->detach();
        $this->getJson('/api/v1/configuracion/cajas')->assertOk()->assertJsonPath('data', []);
    }

    private UsuarioSistema $usuario;

    private AlmacenInventario $propio;

    private AlmacenInventario $ajeno;

    private LoteItem $lote;

    private LoteItem $loteAjeno;

    private string $rol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GestionAccesosSeeder::class);
        $this->rol = (string) Str::uuid();
        DB::table('roles')->insert(['id' => $this->rol, 'codigo' => 'OPERADOR', 'nombre' => 'Operador', 'activo' => true]);
        DB::table('rol_sistema')->insert(['rol_id' => $this->rol, 'sistema_id' => DB::table('sistemas')->where('codigo', 'CONTABLE')->value('id')]);
        foreach (DB::table('permisos')->pluck('id') as $permiso) {
            DB::table('permiso_rol')->insert(['rol_id' => $this->rol, 'permiso_id' => $permiso]);
        }
        $this->usuario = UsuarioSistema::factory()->conRol('OPERADOR')->create();
        $this->propio = AlmacenInventario::factory()->create();
        $this->ajeno = AlmacenInventario::factory()->create(['sucursal_id' => $this->propio->sucursal_id]);
        $this->usuario->sucursalesAsignadas()->attach($this->propio->sucursal_id);
        $this->usuario->almacenesAsignados()->attach($this->propio->id);
        $this->lote = LoteItem::factory()->create();
        $this->loteAjeno = LoteItem::factory()->create();
        ExistenciaLote::factory()->create(['lote_id' => $this->lote->id, 'almacen_id' => $this->propio->id, 'cantidad' => 7]);
        ExistenciaLote::factory()->create(['lote_id' => $this->lote->id, 'almacen_id' => $this->ajeno->id, 'cantidad' => 90]);
        ExistenciaLote::factory()->create(['lote_id' => $this->loteAjeno->id, 'almacen_id' => $this->ajeno->id, 'cantidad' => 30]);
        $this->actingAs($this->usuario, 'contable');
    }

    public function test_solo_ve_stock_y_catalogos_asignados_sin_sumar_otro_almacen_de_su_sucursal(): void
    {
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.almacenId', $this->propio->id)->assertJsonPath('data.0.disponible', 7);
        $this->getJson('/api/v1/inventario/items')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $this->lote->item_id);
        $this->getJson('/api/v1/inventario/productos-base')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/stock/estudios')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.stock', 7);
        $this->getJson('/api/v1/adquisiciones/opciones')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonCount(1, 'data.almacenes');
        $this->getJson('/api/v1/configuracion/general')->assertOk()->assertJsonCount(1, 'data.almacenes')->assertJsonPath('data.sucursales.0.almacenes', 1);
        $this->getJson('/api/v1/usuarios/opciones')->assertOk()->assertJsonCount(1, 'data.almacenes')->assertJsonPath('data.almacenes.0.id', $this->propio->id);
        $this->getJson('/api/v1/inventario/items?producto_base_id='.$this->loteAjeno->item->producto_base_id)->assertOk()->assertJsonPath('total', 0);
    }

    public function test_lotes_ajenos_del_mismo_producto_no_aparecen_ni_en_busqueda(): void
    {
        $otro = LoteItem::factory()->create(['item_id' => $this->lote->item_id, 'numero' => 'LOTE-SECRETO']);
        ExistenciaLote::factory()->create(['lote_id' => $otro->id, 'almacen_id' => $this->ajeno->id]);
        $this->getJson('/api/v1/inventario/items')->assertOk()->assertJsonCount(1, 'data.0.lotes')->assertJsonPath('data.0.lotes.0.id', $this->lote->id);
        $this->getJson('/api/v1/inventario/items?buscar=LOTE-SECRETO')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_catalogo_de_ventas_usa_solo_existencias_autorizadas_y_no_datos_de_ejemplo(): void
    {
        $this->getJson('/api/v1/ventas/catalogo')->assertOk()->assertJsonCount(1, 'almacenes')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.almacenId', $this->propio->id)->assertJsonPath('data.0.stock', 7);
        $this->usuario->almacenesAsignados()->detach();
        $this->getJson('/api/v1/ventas/catalogo')->assertOk()->assertJsonCount(0, 'almacenes')->assertJsonCount(0, 'data');
    }

    public function test_compra_ajena_no_aparece_y_no_puede_registrar_ingresos_fuera_de_su_almacen(): void
    {
        $ingreso = IngresoStock::factory()->create(['almacen_id' => $this->propio->id]);
        IngresoStock::factory()->create(['almacen_id' => $this->ajeno->id]);
        $this->getJson('/api/v1/adquisiciones')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ingreso->datos()['id']);
        $this->postJson('/api/v1/adquisiciones', $this->ingreso($this->ajeno, $this->lote))->assertForbidden();
        $this->postJson('/api/v1/adquisiciones', $this->ingreso($this->propio, $this->loteAjeno))->assertForbidden();
        $this->assertDatabaseCount('ingresos_stock', 2);
        $this->assertDatabaseCount('detalles_ingreso_stock', 0);
    }

    public function test_puede_recibir_en_su_almacen_sin_cambiar_stock_ajeno(): void
    {
        $this->postJson('/api/v1/adquisiciones', $this->ingreso($this->propio, $this->lote))->assertCreated();
        $this->assertDatabaseHas('existencias_lote', ['lote_id' => $this->lote->id, 'almacen_id' => $this->propio->id, 'cantidad' => 9]);
        $this->assertDatabaseHas('existencias_lote', ['lote_id' => $this->lote->id, 'almacen_id' => $this->ajeno->id, 'cantidad' => 90]);
    }

    public function test_no_puede_cambiar_precios_de_lote_compartido_ni_catalogo_global(): void
    {
        $datos = $this->ingreso($this->propio, $this->lote);
        $datos['lineas'][0]['precio1'] = '999.00';
        $this->postJson('/api/v1/adquisiciones', $datos)->assertForbidden();
        $this->postJson('/api/v1/configuracion/sucursales', ['nombre' => 'No permitida', 'direccion' => 'Otra'])->assertForbidden();
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseHas('lotes_items', ['id' => $this->lote->id, 'precio_1' => $this->lote->precio_1]);
    }

    public function test_sin_asignacion_o_con_almacen_o_sucursal_inactivos_no_ve_inventario(): void
    {
        $this->propio->update(['activo' => false]);
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(0, 'data');
        $this->propio->update(['activo' => true]);
        $this->propio->sucursal->update(['activo' => false]);
        $this->getJson('/api/v1/inventario/items')->assertOk()->assertJsonPath('total', 0);
        $this->propio->sucursal->update(['activo' => true]);
        $this->usuario->almacenesAsignados()->detach();
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/adquisiciones/opciones')->assertOk()->assertJsonCount(0, 'data.almacenes')->assertJsonCount(0, 'data.items');
    }

    public function test_dos_almacenes_asignados_muestran_la_union_y_admin_ve_todos(): void
    {
        $this->usuario->almacenesAsignados()->attach($this->ajeno->id);
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(3, 'data');
        $this->usuario->almacenesAsignados()->detach();
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_no_puede_asignar_almacen_ajeno_ni_crear_administrador_con_los_mismos_permisos(): void
    {
        $datos = ['nombres' => 'Nuevo', 'usuario' => 'nuevo', 'password' => 'Password123', 'password_confirmation' => 'Password123',
            'rol_id' => $this->rol, 'sucursal_ids' => [$this->propio->sucursal_id], 'almacen_ids' => [$this->ajeno->id]];
        $this->postJson('/api/v1/usuarios', $datos)->assertUnprocessable()->assertJsonValidationErrors('almacen_ids');
        $datos['almacen_ids'] = [$this->propio->id];
        $datos['rol_id'] = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');
        $this->postJson('/api/v1/usuarios', $datos)->assertUnprocessable()->assertJsonValidationErrors('rol_id');
    }

    private function ingreso(AlmacenInventario $almacen, LoteItem $lote): array
    {
        return ['solicitud_id' => (string) Str::uuid(), 'fecha' => '2026-10-22', 'proveedor' => 'Proveedor', 'almacen_id' => $almacen->id,
            'lineas' => [['item_id' => $lote->item_id, 'lote' => $lote->numero, 'vence' => $lote->vencimiento?->format('Y-m-d'),
                'cantidad' => '2', 'costoUnitario' => '5', 'precio1' => $lote->precio_1, 'precio2' => $lote->precio_2, 'precio3' => $lote->precio_3]]];

    }
}
