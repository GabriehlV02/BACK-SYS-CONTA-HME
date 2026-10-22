<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\ExistenciaLote;
use App\Models\ItemInventario;
use App\Models\LoteItem;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdquisicionesTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        $this->travelTo(now()->setDate(2026, 10, 20));
    }

    private function datos(?ItemInventario $item = null, ?AlmacenInventario $almacen = null): array
    {
        $item ??= ItemInventario::factory()->create();
        $almacen ??= AlmacenInventario::factory()->create();

        return ['solicitud_id' => (string) Str::uuid(), 'fecha' => '2026-10-20', 'proveedor' => 'Proveedor real', 'comprobante' => 'FAC-100', 'almacen_id' => $almacen->id,
            'lineas' => [['item_id' => $item->id, 'lote' => 'LT-001', 'vence' => '2028-10-20', 'cantidad' => '2.500', 'costoUnitario' => '8.1234', 'precio1' => '10.25', 'precio2' => '11.50', 'precio3' => '13.75', 'observacion' => 'Recepción correcta']]];
    }

    public function test_exige_sesion_y_permiso_de_movimientos(): void
    {
        $this->getJson('/api/v1/adquisiciones')->assertUnauthorized();
        $this->postJson('/api/v1/adquisiciones')->assertUnauthorized();
        $this->administrador();
        $id = DB::table('permisos')->where('codigo', 'MOVIMIENTOS_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $id)->delete();
        $this->getJson('/api/v1/adquisiciones')->assertForbidden();
        $this->getJson('/api/v1/adquisiciones/opciones')->assertForbidden();
        $this->postJson('/api/v1/adquisiciones', $this->datos())->assertForbidden();
        $this->postJson('/api/v1/adquisiciones/almacenes', [])->assertForbidden();
        $this->getJson('/api/v1/stock/estudios')->assertForbidden();
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseCount('existencias_lote', 0);
    }

    public function test_sin_ingresos_devuelve_lista_real_vacia(): void
    {
        $this->administrador();
        $this->getJson('/api/v1/adquisiciones')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_registra_recepcion_y_actualiza_stock_con_precios_manuales(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated()
            ->assertJsonPath('data.total', '20.31')->assertJsonPath('data.lineas.0.precio1', '10.25')
            ->assertJsonPath('data.lineas.0.precio2', '11.50')->assertJsonPath('data.lineas.0.precio3', '13.75');
        $this->assertDatabaseHas('existencias_lote', ['almacen_id' => $datos['almacen_id'], 'cantidad' => '2.500', 'costo_unitario' => '8.1234']);
        $this->assertDatabaseHas('ingresos_stock', ['usuario_id' => auth('contable')->id(), 'total' => '20.31']);
        $this->assertDatabaseCount('detalles_ingreso_stock', 1);
        $this->getJson('/api/v1/adquisiciones')->assertJsonPath('data.0.id', $respuesta->json('data.id'));
        $this->getJson('/api/v1/stock/lotes')->assertOk()->assertJsonPath('data.0.disponible', 2.5)->assertJsonPath('data.0.precio3', '13.75');
    }

    public function test_rechaza_tipo_de_item_no_permitido_por_el_almacen_sin_crear_stock(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $tipo = ItemInventario::findOrFail($datos['lineas'][0]['item_id'])->productoBase->tipo->tipo_base;
        AlmacenInventario::findOrFail($datos['almacen_id'])->update(['tipos_item' => [$tipo === 'PRODUCTO' ? 'Insumos' : 'Productos']]);
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('lineas.0.item_id');
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseCount('existencias_lote', 0);
        $this->assertDatabaseCount('lotes_items', 0);
    }

    public function test_reintento_identico_no_duplica_stock_y_cambio_de_payload_da_409(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $primera = $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated();
        $this->postJson('/api/v1/adquisiciones', $datos)->assertOk()->assertJsonPath('data.id', $primera->json('data.id'));
        $datos['lineas'][0]['cantidad'] = '5';
        $this->postJson('/api/v1/adquisiciones', $datos)->assertConflict();
        $this->assertDatabaseCount('ingresos_stock', 1);
        $this->assertDatabaseCount('detalles_ingreso_stock', 1);
        $this->assertDatabaseHas('existencias_lote', ['cantidad' => '2.500']);
    }

    public function test_acumula_lote_existente_y_conserva_historial_de_precios_y_nombres(): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create(['nombre_comercial' => 'Comercial inicial']);
        $datos = $this->datos($item);
        $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated();
        $item->update(['nombre_comercial' => 'Nombre cambiado']);
        $datos['solicitud_id'] = (string) Str::uuid();
        $datos['lineas'][0]['cantidad'] = '1.125';
        $datos['lineas'][0]['precio1'] = '19.00';
        $datos['lineas'][0]['costoUnitario'] = '9.4321';
        $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated()->assertJsonPath('data.total', '10.61');
        $this->assertDatabaseCount('lotes_items', 1);
        $this->assertDatabaseHas('existencias_lote', ['cantidad' => '3.625', 'costo_unitario' => '9.4321']);
        $this->getJson('/api/v1/adquisiciones')->assertJsonPath('data.1.lineas.0.producto', 'Comercial inicial')->assertJsonPath('data.1.lineas.0.precio1', '10.25');
        $this->assertSame('10.00', $item->refresh()->precio_1);
    }

    public function test_mismo_lote_en_almacenes_diferentes_no_mezcla_existencias(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated();
        $datos['solicitud_id'] = (string) Str::uuid();
        $datos['almacen_id'] = AlmacenInventario::factory()->create()->id;
        $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated();
        $this->assertDatabaseCount('existencias_lote', 2);
        $this->assertSame(5.0, (float) DB::table('existencias_lote')->sum('cantidad'));
    }

    public function test_revierte_todo_si_una_linea_posterior_es_invalida(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $inactivo = ItemInventario::factory()->create(['estado' => 'INACTIVO']);
        $datos['lineas'][] = [...$datos['lineas'][0], 'item_id' => $inactivo->id];
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('lineas.1.item_id');
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseCount('detalles_ingreso_stock', 0);
        $this->assertDatabaseCount('existencias_lote', 0);
        $this->assertDatabaseCount('lotes_items', 0);
    }

    public function test_rechaza_lotes_duplicados_y_vencimiento_inconsistente(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $datos['lineas'][] = [...$datos['lineas'][0], 'lote' => ' lt-001 '];
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('lineas.1.lote');
        $this->assertDatabaseCount('existencias_lote', 0);
        array_pop($datos['lineas']);
        LoteItem::factory()->create(['item_id' => $datos['lineas'][0]['item_id'], 'numero' => 'LT-001', 'clave' => 'LT-001', 'vencimiento' => '2029-01-01']);
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('lineas.0.vence');
        $this->assertDatabaseCount('ingresos_stock', 0);
    }

    #[TestWith(['cantidad', '0'])]
    #[TestWith(['cantidad', '-1'])]
    #[TestWith(['cantidad', '1.2345'])]
    #[TestWith(['cantidad', '100001'])]
    #[TestWith(['costoUnitario', '-1'])]
    #[TestWith(['costoUnitario', '1.12345'])]
    #[TestWith(['precio1', '-1'])]
    #[TestWith(['precio2', '1.234'])]
    #[TestWith(['precio3', '-1'])]
    public function test_rechaza_importes_y_cantidades_invalidos_sin_movimientos(string $campo, string $valor): void
    {
        $this->administrador();
        $datos = $this->datos();
        $datos['lineas'][0][$campo] = $valor;
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('lineas.0.'.$campo);
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseCount('existencias_lote', 0);
    }

    public function test_rechaza_fecha_futura_y_datos_obligatorios(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/adquisiciones', [])->assertUnprocessable()->assertJsonValidationErrors(['solicitud_id', 'fecha', 'proveedor', 'almacen_id', 'lineas']);
        $datos = $this->datos();
        $datos['fecha'] = '2026-10-21';
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonPath('errors.fecha.0', 'La recepción no puede tener una fecha futura.');
    }

    public function test_rechaza_almacen_inactivo(): void
    {
        $this->administrador();
        $datos = $this->datos(almacen: AlmacenInventario::factory()->create(['activo' => false]));
        $this->postJson('/api/v1/adquisiciones', $datos)->assertUnprocessable()->assertJsonValidationErrors('almacen_id');
        $this->assertDatabaseCount('ingresos_stock', 0);
    }

    public function test_opciones_excluyen_inactivos_y_devuelven_lotes_comerciales(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create();
        ItemInventario::factory()->create(['estado' => 'INACTIVO']);
        $almacen = AlmacenInventario::factory()->create();
        AlmacenInventario::factory()->create(['activo' => false]);
        $this->getJson('/api/v1/adquisiciones/opciones')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonCount(1, 'data.almacenes')
            ->assertJsonPath('data.items.0.lotes.0.id', $lote->id)->assertJsonPath('data.almacenes.0.id', $almacen->id)
            ->assertJsonPath('data.items.0.grupoId', $lote->item->producto_base_id)
            ->assertJsonPath('data.items.0.codigoGenerico', $lote->item->productoBase->codigo);
    }

    public function test_registra_destino_real_y_no_duplica_codigo(): void
    {
        $this->administrador();
        $datos = ['nombre' => 'Farmacia', 'codigo' => 'FAR-01', 'sucursal_nombre' => 'Casa matriz'];
        $respuesta = $this->postJson('/api/v1/adquisiciones/almacenes', $datos)->assertCreated()->assertJsonPath('data.sucursal.nombre', 'Casa matriz');
        $this->postJson('/api/v1/adquisiciones/almacenes', [...$datos, 'sucursal_id' => $respuesta->json('data.sucursal.id')])->assertUnprocessable()->assertJsonValidationErrors('codigo');
        $this->assertDatabaseCount('almacenes_inventario', 1);
        $this->assertDatabaseCount('sucursales_inventario', 1);
    }

    public function test_estudios_suman_existencias_reales_sin_inventar_ventas(): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create();
        $lote = LoteItem::factory()->create(['item_id' => $item->id]);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'cantidad' => '2.125']);
        ExistenciaLote::factory()->create(['lote_id' => $lote->id, 'cantidad' => '3.250']);
        $this->getJson('/api/v1/stock/estudios')->assertOk()->assertJsonPath('data.0.stock', 5.375)->assertJsonPath('meta.ventas_conectadas', false);
    }

    public function test_varios_productos_redondean_por_linea_e_ignoran_totales_y_nombres_del_cliente(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $datos['lineas'][0]['cantidad'] = '1';
        $datos['lineas'][0]['costoUnitario'] = '0.0050';
        $datos['lineas'][0]['producto'] = 'Nombre falsificado';
        $datos['total'] = '-999';
        $otro = ItemInventario::factory()->create();
        $datos['lineas'][] = [...$datos['lineas'][0], 'item_id' => $otro->id];
        $this->postJson('/api/v1/adquisiciones', $datos)->assertCreated()->assertJsonPath('data.total', '0.02')
            ->assertJsonPath('data.lineas.0.subtotal', '0.01')->assertJsonPath('data.lineas.1.subtotal', '0.01')
            ->assertJsonPath('data.lineas.1.producto', $otro->nombre_comercial);
        $this->assertDatabaseCount('detalles_ingreso_stock', 2);
        $this->assertDatabaseCount('existencias_lote', 2);
    }

    #[TestWith(['agrupador'])]
    #[TestWith(['tipo'])]
    #[TestWith(['unidad'])]
    public function test_rechaza_catalogos_inactivos_aunque_el_item_este_activo(string $catalogo): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create();
        match ($catalogo) {
            'agrupador' => $item->productoBase->update(['estado' => 'INACTIVO']),
            'tipo' => $item->productoBase->tipo->update(['activo' => false]),
            'unidad' => $item->unidad->update(['activo' => false]),
        };
        $this->postJson('/api/v1/adquisiciones', $this->datos($item))->assertUnprocessable()->assertJsonValidationErrors('lineas.0.item_id');
        $this->getJson('/api/v1/adquisiciones/opciones')->assertJsonCount(0, 'data.items');
        $this->assertDatabaseCount('ingresos_stock', 0);
        $this->assertDatabaseCount('existencias_lote', 0);
    }
}
