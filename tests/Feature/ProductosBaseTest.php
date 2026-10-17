<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\CategoriaInventario;
use App\Models\ProductoBase;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductosBaseTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }

    private function datos(): array
    {
        return ['nombre' => 'Paracetamol', 'tipo_id' => TipoInventario::factory()->create()->id, 'estado' => 'ACTIVO'];
    }

    public function test_registra_producto_base_sin_presentacion_precio_ni_lote(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/inventario/productos-base', $datos)
            ->assertCreated()->assertJsonPath('data.nombre', 'Paracetamol');
        $id = $respuesta->json('data.id');
        $respuesta->assertJsonPath('data.codigo', 'PRD-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT));
        $this->assertDatabaseHas('productos_base', ['id' => $id, 'clave' => 'PARACETAMOL', 'categoria_id' => null]);
        $this->getJson('/api/v1/inventario/productos-base')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('total', 1);
        $this->getJson('/api/v1/inventario/configuracion')->assertOk()->assertJsonPath('data.tipos.0.cantidad', 1);
    }

    public function test_rechaza_nombre_duplicado_normalizado(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertCreated();
        $datos['nombre'] = '  PARACETAMOL  ';
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('clave');
        $this->assertDatabaseCount('productos_base', 1);
    }

    public function test_actualiza_producto_y_conserva_codigo(): void
    {
        $this->administrador();
        $producto = ProductoBase::factory()->create();
        $codigo = $producto->codigo;
        $datos = ['nombre' => 'Paracetamol', 'tipo_id' => $producto->tipo_id, 'estado' => 'INACTIVO'];
        $this->putJson('/api/v1/inventario/productos-base/'.$producto->id, $datos)->assertOk()->assertJsonPath('data.codigo', $codigo);
        $this->putJson('/api/v1/inventario/productos-base/'.$producto->id, $datos)->assertOk();
        $this->assertDatabaseHas('productos_base', ['id' => $producto->id, 'nombre' => 'Paracetamol', 'estado' => 'INACTIVO']);
    }

    public static function camposDeItem(): array
    {
        return array_map(fn (string $campo): array => [$campo], ['precio', 'precio_compra', 'precio_venta', 'stock', 'lote', 'presentacion', 'vencimiento', 'codigo_barras']);
    }

    #[DataProvider('camposDeItem')]
    public function test_rechaza_datos_que_pertenecen_al_item(string $campo): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/productos-base', [...$this->datos(), $campo => '10'])
            ->assertUnprocessable()->assertJsonValidationErrors($campo);
        $this->assertDatabaseCount('productos_base', 0);
    }

    public function test_rechaza_tipo_servicio_e_inactivo(): void
    {
        $this->administrador();
        $datos = $this->datos();
        TipoInventario::findOrFail($datos['tipo_id'])->update(['tipo_base' => 'SERVICIO']);
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
        TipoInventario::findOrFail($datos['tipo_id'])->update(['tipo_base' => 'PRODUCTO', 'activo' => false]);
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
    }

    public function test_subcategoria_debe_corresponder_a_categoria_activa(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $categoria = CategoriaInventario::factory()->create();
        $subcategoria = SubcategoriaInventario::factory()->create();
        $datos['subcategoria_id'] = $subcategoria->id;
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('subcategoria_id');
        $datos['categoria_id'] = $categoria->id;
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('subcategoria_id');
        $datos['categoria_id'] = $subcategoria->categoria_id;
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertCreated();
        $subcategoria->categoria->update(['activo' => false]);
        $datos['nombre'] = 'Ibuprofeno';
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
    }

    public function test_busca_filtra_y_pagina_productos_base(): void
    {
        $this->administrador();
        $producto = ProductoBase::factory()->create(['nombre' => 'Paracetamol', 'clave' => 'PARACETAMOL']);
        ProductoBase::factory()->create(['estado' => 'INACTIVO']);
        $this->getJson('/api/v1/inventario/productos-base?buscar=paracetamol')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $producto->id);
        $this->getJson('/api/v1/inventario/productos-base?buscar='.$producto->codigo)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/inventario/productos-base?estado=INACTIVO')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/inventario/productos-base?por_pagina=1&page=2')->assertOk()->assertJsonPath('current_page', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/inventario/productos-base?orden=precio')->assertUnprocessable();
    }

    public function test_lectura_no_permite_escritura_y_sin_permisos_no_permite_lectura(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $producto = ProductoBase::factory()->create();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/productos-base')->assertOk();
        $this->postJson('/api/v1/inventario/productos-base', $datos)->assertForbidden();
        $this->putJson('/api/v1/inventario/productos-base/'.$producto->id, $datos)->assertForbidden();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_VER')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/productos-base')->assertForbidden();
    }

    public function test_exige_sesion_y_datos_obligatorios(): void
    {
        $this->getJson('/api/v1/inventario/productos-base')->assertUnauthorized();
        $this->postJson('/api/v1/inventario/productos-base', [])->assertUnauthorized();
        $this->administrador();
        $this->postJson('/api/v1/inventario/productos-base', [])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'tipo_id', 'estado']);
        $this->putJson('/api/v1/inventario/productos-base/999999', $this->datos())->assertNotFound();
    }
}
