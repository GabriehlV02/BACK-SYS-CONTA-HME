<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\ExistenciaLote;
use App\Models\ItemInventario;
use App\Models\LoteItem;
use App\Models\SucursalInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }

    public function test_exige_sesion_y_permiso_de_consulta(): void
    {
        $this->getJson('/api/v1/stock/lotes')->assertUnauthorized();
        $this->administrador();
        $permisos = DB::table('permisos')->whereIn('codigo', ['INVENTARIO_VER', 'INVENTARIO_GESTIONAR'])->pluck('id');
        DB::table('permiso_rol')->whereIn('permiso_id', $permisos)->delete();
        $this->getJson('/api/v1/stock/lotes')->assertForbidden();
    }

    public function test_permiso_solo_lectura_consulta_existencias(): void
    {
        $this->administrador();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/stock/lotes')->assertOk();
    }

    public function test_no_inventa_existencias_ni_lotes(): void
    {
        $this->administrador();
        ItemInventario::factory()->create();
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertExactJson(['data' => [], 'meta' => ['registros' => 0, 'sin_ingresos' => 0]]);
        $this->assertDatabaseCount('existencias_lote', 0);
    }

    public function test_lote_sin_compra_muestra_cero_y_costo_desconocido(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create(['precio_1' => '10.25', 'precio_2' => '12.50', 'precio_3' => '15.75']);
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.loteId', $lote->id)->assertJsonPath('data.0.disponible', 0)
            ->assertJsonPath('data.0.almacenId', null)->assertJsonPath('data.0.sucursalId', null)->assertJsonPath('data.0.costoUnitario', null)
            ->assertJsonPath('data.0.precio1', '10.25')->assertJsonPath('data.0.precio2', '12.50')->assertJsonPath('data.0.precio3', '15.75')
            ->assertJsonPath('meta.sin_ingresos', 1);
        $this->assertDatabaseCount('existencias_lote', 0);
    }

    public function test_consulta_lote_en_dos_almacenes_sin_duplicar_cantidades(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create();
        $sucursal = SucursalInventario::factory()->create(['nombre' => 'Casa matriz']);
        $primero = AlmacenInventario::factory()->create(['sucursal_id' => $sucursal->id, 'nombre' => 'Farmacia']);
        $segundo = AlmacenInventario::factory()->create(['sucursal_id' => $sucursal->id, 'nombre' => 'Central']);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'almacen_id' => $primero->id, 'cantidad' => '10.125', 'costo_unitario' => '8.4321']);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'almacen_id' => $segundo->id, 'cantidad' => '20.250']);
        $respuesta = $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.sin_ingresos', 0);
        $filas = collect($respuesta->json('data'));
        $this->assertEquals(30.375, $filas->sum('disponible'));
        $this->assertCount(2, $filas->pluck('id')->unique());
        $this->assertSame('8.4321', $filas->firstWhere('almacenId', $primero->id)['costoUnitario']);
        $this->assertSame('Casa matriz', $filas->first()['sucursal']);
    }

    public function test_almacenes_del_mismo_nombre_se_distinguen_por_sucursal_e_id(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create();
        $primero = AlmacenInventario::factory()->create(['nombre' => 'Farmacia']);
        $segundo = AlmacenInventario::factory()->create(['nombre' => 'Farmacia']);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'almacen_id' => $primero->id]);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'almacen_id' => $segundo->id]);
        $respuesta = $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(2, 'data');
        $this->assertCount(2, collect($respuesta->json('data'))->pluck('almacenId')->unique());
        $this->assertCount(2, collect($respuesta->json('data'))->pluck('sucursalId')->unique());
    }

    public function test_muestra_datos_comerciales_generico_y_vencimientos_reales(): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create(['nombre_comercial' => 'Comercial real', 'marca' => 'Marca real', 'presentacion' => '500 mg']);
        $item->productoBase->update(['nombre' => 'Paracetamol', 'categoria_id' => null, 'subcategoria_id' => null]);
        $lote = LoteItem::factory()->create(['item_id' => $item->id, 'vencimiento' => '2025-01-02']);
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonPath('data.0.nombre', 'Comercial real')->assertJsonPath('data.0.generico', 'Paracetamol')
            ->assertJsonPath('data.0.codigo', $item->codigo)->assertJsonPath('data.0.marca', 'Marca real')->assertJsonPath('data.0.presentacion', '500 mg')
            ->assertJsonPath('data.0.vence', '2025-01-02')->assertJsonPath('data.0.lote', $lote->numero)->assertJsonPath('data.0.categoria', 'Sin categoría');
    }

    public function test_conserva_lotes_sin_fecha_y_almacenes_inactivos_en_el_historico(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create(['vencimiento' => null]);
        $almacen = AlmacenInventario::factory()->create(['activo' => false]);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'almacen_id' => $almacen->id, 'cantidad' => '0.000', 'fecha_ingreso' => null]);
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonPath('data.0.vence', '')->assertJsonPath('data.0.fecha', '')
            ->assertJsonPath('data.0.disponible', 0)->assertJsonPath('data.0.almacenId', $almacen->id);
    }

    public function test_no_hay_ruta_para_alterar_stock_desde_esta_consulta(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/stock/lotes', ['cantidad' => 100])->assertStatus(405);
        $this->assertDatabaseCount('existencias_lote', 0);
    }

    public function test_carga_relaciones_sin_una_consulta_por_cada_lote(): void
    {
        $this->administrador();
        ExistenciaLote::factory()->count(12)->create();
        DB::enableQueryLog();
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonCount(12, 'data');
        $consultas = collect(DB::getQueryLog())->filter(fn (array $consulta): bool => str_starts_with(strtolower($consulta['query']), 'select'));
        DB::disableQueryLog();
        $this->assertLessThan(35, $consultas->count());
    }
}
