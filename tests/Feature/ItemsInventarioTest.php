<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\ItemInventario;
use App\Models\LoteItem;
use App\Models\ProductoBase;
use App\Models\UnidadInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ItemsInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }

    private function datos(?ProductoBase $grupo = null): array
    {
        $grupo ??= ProductoBase::factory()->create(['nombre' => 'Paracetamol', 'clave' => 'PARACETAMOL']);

        return ['producto_base_id' => $grupo->id, 'nombre_comercial' => 'Comercial A', 'marca' => 'Laboratorio A', 'presentacion' => 'Tabletas 500 mg caja de 20', 'unidad_id' => UnidadInventario::factory()->create()->id, 'precio_1' => '10.25', 'precio_2' => '12.50', 'precio_3' => '15.75', 'estado' => 'ACTIVO'];
    }

    private function lote(string $numero = 'LT-001'): array
    {
        return ['numero' => $numero, 'vencimiento' => '2028-10-01', 'precio_1' => '20.25', 'precio_2' => '22.50', 'precio_3' => '25.75'];
    }

    public function test_varios_items_comerciales_comparten_agrupador_y_tienen_codigos_propios(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $primero = $this->postJson('/api/v1/inventario/items', $datos)->assertCreated()->assertJsonPath('data.precio_1', '10.25')->assertJsonPath('data.producto_base.nombre', 'Paracetamol');
        $segundo = $this->postJson('/api/v1/inventario/items', [...$datos, 'nombre_comercial' => 'Comercial B', 'marca' => 'Laboratorio B'])->assertCreated();
        $this->assertNotEquals($primero->json('data.codigo'), $segundo->json('data.codigo'));
        $this->assertDatabaseCount('productos_base', 1);
        $this->assertDatabaseCount('items_inventario', 2);
        $this->getJson('/api/v1/inventario/items?producto_base_id='.$datos['producto_base_id'])->assertOk()->assertJsonPath('total', 2);
    }

    public function test_no_duplica_variante_por_mayusculas_acentos_o_espacios(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $this->postJson('/api/v1/inventario/items', $datos)->assertCreated();
        $this->postJson('/api/v1/inventario/items', [...$datos, 'nombre_comercial' => '  COMERCIAL  A  ', 'marca' => 'LABORATORIO A'])->assertUnprocessable()->assertJsonValidationErrors('clave');
        $this->postJson('/api/v1/inventario/items', [...$datos, 'presentacion' => 'Jarabe 120 ml'])->assertCreated();
        $this->assertDatabaseCount('items_inventario', 2);
    }

    public function test_no_permite_codigo_de_barras_repetido(): void
    {
        $this->administrador();
        $datos = [...$this->datos(), 'codigo_barras' => '789012345'];
        $this->postJson('/api/v1/inventario/items', $datos)->assertCreated();
        $this->postJson('/api/v1/inventario/items', [...$datos, 'marca' => 'Otra marca'])->assertUnprocessable()->assertJsonValidationErrors('codigo_barras');
    }

    public static function preciosInvalidos(): array
    {
        $casos = [];
        foreach (['precio_1', 'precio_2', 'precio_3'] as $campo) {
            foreach (['-1', '1.234', 'abc', '10000000000'] as $valor) {
                $casos[] = [$campo, $valor];
            }
        }

        return $casos;
    }

    #[DataProvider('preciosInvalidos')]
    public function test_valida_los_tres_precios_manuales(string $campo, string $valor): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/items', [...$this->datos(), $campo => $valor])->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('items_inventario', 0);
    }

    public function test_actualiza_datos_sin_cambiar_codigo_o_agrupador(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/inventario/items', $datos)->assertCreated();
        $id = $respuesta->json('data.id');
        $this->putJson('/api/v1/inventario/items/'.$id, [...$datos, 'precio_3' => '99.99', 'estado' => 'INACTIVO'])->assertOk()->assertJsonPath('data.codigo', $respuesta->json('data.codigo'));
        $otro = ProductoBase::factory()->create();
        $this->putJson('/api/v1/inventario/items/'.$id, [...$datos, 'producto_base_id' => $otro->id])->assertUnprocessable()->assertJsonValidationErrors('producto_base_id');
        $this->assertDatabaseHas('items_inventario', ['id' => $id, 'precio_3' => '99.99', 'estado' => 'INACTIVO']);
    }

    public function test_rechaza_agrupador_inactivo_y_unidad_inactiva(): void
    {
        $this->administrador();
        $datos = $this->datos();
        ProductoBase::findOrFail($datos['producto_base_id'])->update(['estado' => 'INACTIVO']);
        UnidadInventario::findOrFail($datos['unidad_id'])->update(['activo' => false]);
        $this->postJson('/api/v1/inventario/items', $datos)->assertUnprocessable()->assertJsonValidationErrors(['producto_base_id', 'unidad_id']);
    }

    public function test_opciones_excluyen_servicios_y_tipos_inactivos(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $this->getJson('/api/v1/inventario/items/opciones')->assertOk()->assertJsonCount(1, 'data.productos');
        $grupo = ProductoBase::findOrFail($datos['producto_base_id']);
        $grupo->tipo->update(['tipo_base' => 'SERVICIO']);
        $this->getJson('/api/v1/inventario/items/opciones')->assertOk()->assertJsonCount(0, 'data.productos');
        $this->postJson('/api/v1/inventario/items', $datos)->assertUnprocessable();
        $grupo->tipo->update(['tipo_base' => 'PRODUCTO', 'activo' => false]);
        $this->postJson('/api/v1/inventario/items', $datos)->assertUnprocessable();
    }

    public function test_registra_varios_lotes_con_precios_independientes(): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create();
        $primero = $this->postJson('/api/v1/inventario/items/'.$item->id.'/lotes', $this->lote())->assertCreated()->assertJsonPath('data.precio_1', '20.25');
        $this->postJson('/api/v1/inventario/items/'.$item->id.'/lotes', [...$this->lote('LT-002'), 'precio_1' => '30.00'])->assertCreated();
        $this->putJson('/api/v1/inventario/items/'.$item->id, ['producto_base_id' => $item->producto_base_id, 'nombre_comercial' => $item->nombre_comercial, 'marca' => $item->marca, 'presentacion' => $item->presentacion, 'unidad_id' => $item->unidad_id, 'estado' => 'ACTIVO', 'precio_1' => '99.99', 'precio_2' => '98.00', 'precio_3' => '97.00'])->assertOk();
        $this->assertDatabaseHas('lotes_items', ['id' => $primero->json('data.id'), 'precio_1' => '20.25']);
        $this->getJson('/api/v1/inventario/items')->assertOk()->assertJsonCount(2, 'data.0.lotes');
    }

    public function test_lote_unico_por_item_y_reutilizable_en_otro(): void
    {
        $this->administrador();
        $primero = ItemInventario::factory()->create();
        $segundo = ItemInventario::factory()->create();
        $this->postJson('/api/v1/inventario/items/'.$primero->id.'/lotes', $this->lote())->assertCreated();
        $this->postJson('/api/v1/inventario/items/'.$primero->id.'/lotes', [...$this->lote(), 'numero' => ' lt-001 '])->assertUnprocessable()->assertJsonValidationErrors('clave');
        $this->postJson('/api/v1/inventario/items/'.$segundo->id.'/lotes', $this->lote())->assertCreated();
    }

    public function test_no_modifica_lote_de_otro_item(): void
    {
        $this->administrador();
        $lote = LoteItem::factory()->create();
        $otro = ItemInventario::factory()->create();
        $this->putJson('/api/v1/inventario/items/'.$otro->id.'/lotes/'.$lote->id, $this->lote())->assertNotFound();
        $this->assertDatabaseHas('lotes_items', ['id' => $lote->id, 'precio_1' => '10.00']);
        $this->putJson('/api/v1/inventario/items/'.$lote->item_id.'/lotes/'.$lote->id, $this->lote())->assertOk();
        $this->assertDatabaseHas('lotes_items', ['id' => $lote->id, 'precio_1' => '20.25', 'vencimiento' => '2028-10-01']);
    }

    public function test_no_agrega_lotes_a_item_inactivo_y_valida_fecha_precios_y_cantidades(): void
    {
        $this->administrador();
        $item = ItemInventario::factory()->create(['estado' => 'INACTIVO']);
        $this->postJson('/api/v1/inventario/items/'.$item->id.'/lotes', $this->lote())->assertUnprocessable();
        $item->update(['estado' => 'ACTIVO']);
        $this->postJson('/api/v1/inventario/items/'.$item->id.'/lotes', [...$this->lote(), 'vencimiento' => '2028-02-31', 'precio_2' => '-1', 'cantidad' => 20])->assertUnprocessable()->assertJsonValidationErrors(['vencimiento', 'precio_2', 'cantidad']);
        $this->assertDatabaseCount('lotes_items', 0);
    }

    public function test_busca_por_nombre_generico_codigo_marca_y_lote_y_pagina(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/inventario/items', $datos)->assertCreated();
        $this->postJson('/api/v1/inventario/items/'.$respuesta->json('data.id').'/lotes', $this->lote('LTX-2028'))->assertCreated();
        foreach (['Paracetamol', 'Laboratorio A', $respuesta->json('data.codigo'), 'LTX-2028', $respuesta->json('data.producto_base.codigo')] as $buscar) {
            $this->getJson('/api/v1/inventario/items?buscar='.urlencode($buscar))->assertOk()->assertJsonPath('total', 1);
        }
        ItemInventario::factory()->create();
        $this->getJson('/api/v1/inventario/items?por_pagina=1&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('current_page', 2);
        $this->getJson('/api/v1/inventario/items?orden=clave')->assertUnprocessable();
    }

    public function test_sin_permisos_no_escribe_y_sin_sesion_no_consulta(): void
    {
        $this->getJson('/api/v1/inventario/items')->assertUnauthorized();
        $this->getJson('/api/v1/inventario/items/opciones')->assertUnauthorized();
        $this->administrador();
        $datos = $this->datos();
        $item = ItemInventario::factory()->create();
        $lote = LoteItem::factory()->create(['item_id' => $item->id]);
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/items')->assertOk();
        $this->postJson('/api/v1/inventario/items', $datos)->assertForbidden();
        $this->putJson('/api/v1/inventario/items/'.$item->id, $datos)->assertForbidden();
        $this->postJson('/api/v1/inventario/items/'.$item->id.'/lotes', $this->lote())->assertForbidden();
        $this->putJson('/api/v1/inventario/items/'.$item->id.'/lotes/'.$lote->id, $this->lote())->assertForbidden();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_VER')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/items')->assertForbidden();
    }

    public function test_exige_datos_y_no_acepta_existencias_directas(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/items', [])->assertUnprocessable()->assertJsonValidationErrors(['producto_base_id', 'nombre_comercial', 'marca', 'presentacion', 'unidad_id', 'precio_1', 'precio_2', 'precio_3', 'estado']);
        $this->postJson('/api/v1/inventario/items', [...$this->datos(), 'stock' => 50])->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_identificadores_invalidos_devuelven_validacion_y_no_error_sql(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/items', [...$this->datos(), 'producto_base_id' => 'invalido', 'unidad_id' => 'invalida'])
            ->assertUnprocessable()->assertJsonValidationErrors(['producto_base_id', 'unidad_id']);
    }
}
