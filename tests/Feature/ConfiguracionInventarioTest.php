<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\CategoriaInventario;
use App\Models\ClasificacionInventario;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use App\Models\UnidadInventario;
use Database\Seeders\ConfiguracionInventarioSeeder;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfiguracionInventarioTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): UsuarioSistema
    {
        $this->seed(GestionAccesosSeeder::class);
        $usuario = UsuarioSistema::factory()->conRol()->create();
        $this->actingAs($usuario, 'contable');

        return $usuario;
    }

    public static function catalogos(): array
    {
        return [
            'tipos' => ['tipos', 'tipos_inventario', ['nombre' => 'Material especial', 'descripcion' => 'Material de uso especial', 'tipo_base' => 'INSUMO'], 'MATERIAL ESPECIAL'],
            'unidades' => ['unidades', 'unidades_inventario', ['nombre' => 'Caja'], 'CAJA'],
            'categorias' => ['categorias', 'categorias_inventario', ['nombre' => 'Fármacos', 'prefijo' => 'far'], 'FARMACOS'],
            'subcategorias' => ['subcategorias', 'subcategorias_inventario', ['nombre' => 'Inyectable'], 'INYECTABLE'],
            'clasificaciones' => ['clasificaciones', 'clasificaciones_inventario', ['nombre' => 'Antibióticos'], 'ANTIBIOTICOS'],
        ];
    }

    #[DataProvider('catalogos')]
    public function test_registra_cada_catalogo_en_postgresql_y_lo_lista(string $catalogo, string $tabla, array $datos, string $clave): void
    {
        $this->administrador();
        if ($catalogo === 'subcategorias') {
            $datos['categoria_id'] = CategoriaInventario::factory()->create()->id;
        }

        $response = $this->postJson('/api/v1/inventario/configuracion/'.$catalogo, $datos)
            ->assertCreated()->assertJsonPath('data.clave', $clave)->assertJsonPath('data.activo', true);
        $id = $response->json('data.id');
        $this->assertDatabaseHas($tabla, ['id' => $id, 'clave' => $clave, 'activo' => true]);
        $this->getJson('/api/v1/inventario/configuracion')->assertOk()->assertJsonPath('data.'.$catalogo.'.0.id', $id);
    }

    public function test_carga_tres_tipos_iniciales_sin_llenar_los_demas_catalogos(): void
    {
        $this->administrador();
        $this->seed(ConfiguracionInventarioSeeder::class);

        $this->getJson('/api/v1/inventario/configuracion')->assertOk()->assertJsonCount(3, 'data.tipos')
            ->assertJsonCount(0, 'data.unidades')->assertJsonCount(0, 'data.categorias')
            ->assertJsonCount(0, 'data.subcategorias')->assertJsonCount(0, 'data.clasificaciones');
    }

    public function test_rechaza_duplicados_aunque_cambien_mayusculas_o_tildes(): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/configuracion/unidades', ['nombre' => 'Cápsulas'])->assertCreated();

        $this->postJson('/api/v1/inventario/configuracion/unidades', ['nombre' => 'CAPSULAS'])
            ->assertUnprocessable()->assertJsonPath('errors.clave.0', 'Ya existe un registro con ese nombre.');
        $this->assertDatabaseCount('unidades_inventario', 1);
    }

    public function test_categoria_exige_prefijo_y_rechaza_prefijo_repetido(): void
    {
        $this->administrador();
        CategoriaInventario::factory()->create(['prefijo' => 'FAR']);

        $this->postJson('/api/v1/inventario/configuracion/categorias', ['nombre' => 'Farmacia'])
            ->assertUnprocessable()->assertJsonValidationErrors(['prefijo']);
        $this->postJson('/api/v1/inventario/configuracion/categorias', ['nombre' => 'Farmacia', 'prefijo' => 'far'])
            ->assertUnprocessable()->assertJsonValidationErrors(['prefijo']);
    }

    public function test_subcategoria_exige_una_categoria_activa(): void
    {
        $this->administrador();
        $categoria = CategoriaInventario::factory()->create(['activo' => false]);

        $this->postJson('/api/v1/inventario/configuracion/subcategorias', ['nombre' => 'Inyectable', 'categoria_id' => $categoria->id])
            ->assertUnprocessable()->assertJsonPath('errors.categoria_id.0', 'Selecciona una categoría activa.');
        $this->assertDatabaseCount('subcategorias_inventario', 0);
    }

    public function test_subcategorias_pueden_repetir_nombre_en_categorias_diferentes(): void
    {
        $this->administrador();
        $primera = CategoriaInventario::factory()->create();
        $segunda = CategoriaInventario::factory()->create();

        $this->postJson('/api/v1/inventario/configuracion/subcategorias', ['nombre' => 'Otros', 'categoria_id' => $primera->id])->assertCreated();
        $this->postJson('/api/v1/inventario/configuracion/subcategorias', ['nombre' => 'Otros', 'categoria_id' => $segunda->id])->assertCreated();
        $this->postJson('/api/v1/inventario/configuracion/subcategorias', ['nombre' => 'OTROS', 'categoria_id' => $primera->id])->assertUnprocessable();
        $this->assertDatabaseCount('subcategorias_inventario', 2);
    }

    public function test_opciones_devuelve_solo_registros_y_categorias_activas(): void
    {
        $this->administrador();
        $this->seed(ConfiguracionInventarioSeeder::class);
        UnidadInventario::factory()->create(['nombre' => 'Caja', 'clave' => 'CAJA']);
        UnidadInventario::factory()->create(['clave' => 'INACTIVA', 'activo' => false]);
        ClasificacionInventario::factory()->create(['clave' => 'FAMILIA']);
        $categoria = CategoriaInventario::factory()->create(['clave' => 'FARMACOS', 'prefijo' => 'FAR']);
        SubcategoriaInventario::factory()->create(['categoria_id' => $categoria->id, 'clave' => 'INYECTABLE']);
        SubcategoriaInventario::factory()->create(['categoria_id' => $categoria->id, 'clave' => 'INACTIVA', 'activo' => false]);
        $oculta = CategoriaInventario::factory()->create(['clave' => 'OCULTA', 'activo' => false]);
        SubcategoriaInventario::factory()->create(['categoria_id' => $oculta->id]);

        $this->getJson('/api/v1/inventario/opciones')->assertOk()
            ->assertJsonPath('data.unidades', ['CAJA'])->assertJsonPath('data.tipos.SERVICIO', 'SERVICIO')
            ->assertJsonPath('data.categorias.FARMACOS.prefijo', 'FAR')
            ->assertJsonPath('data.categorias.FARMACOS.subcategorias', ['INYECTABLE'])
            ->assertJsonMissingPath('data.categorias.OCULTA')->assertJsonPath('data.clasificaciones', ['FAMILIA']);
    }

    public function test_cambio_de_estado_se_persiste_y_actualiza_las_opciones(): void
    {
        $this->administrador();
        $unidad = UnidadInventario::factory()->create(['clave' => 'CAJA']);

        $this->patchJson('/api/v1/inventario/configuracion/unidades/'.$unidad->id, ['activo' => false])
            ->assertOk()->assertJsonPath('data.activo', false);
        $this->assertFalse($unidad->fresh()->activo);
        $this->getJson('/api/v1/inventario/opciones')->assertOk()->assertJsonPath('data.unidades', []);
        $this->patchJson('/api/v1/inventario/configuracion/unidades/'.$unidad->id, ['activo' => true])->assertOk();
        $this->getJson('/api/v1/inventario/opciones')->assertOk()->assertJsonPath('data.unidades', ['CAJA']);
    }

    public function test_no_activa_subcategoria_si_su_categoria_esta_inactiva(): void
    {
        $this->administrador();
        $categoria = CategoriaInventario::factory()->create(['activo' => false]);
        $subcategoria = SubcategoriaInventario::factory()->create(['categoria_id' => $categoria->id, 'activo' => false]);

        $this->patchJson('/api/v1/inventario/configuracion/subcategorias/'.$subcategoria->id, ['activo' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['activo']);
        $this->assertFalse($subcategoria->fresh()->activo);
    }

    public function test_cambio_de_estado_no_permite_modificar_el_nombre(): void
    {
        $this->administrador();
        $unidad = UnidadInventario::factory()->create(['nombre' => 'Caja']);

        $this->patchJson('/api/v1/inventario/configuracion/unidades/'.$unidad->id, ['activo' => false, 'nombre' => 'Alterado'])->assertOk();
        $this->assertSame('Caja', $unidad->fresh()->nombre);
    }

    public function test_sin_sesion_no_puede_consultar_ni_registrar_catalogos(): void
    {
        $this->getJson('/api/v1/inventario/configuracion')->assertUnauthorized();
        $this->getJson('/api/v1/inventario/opciones')->assertUnauthorized();
        $this->postJson('/api/v1/inventario/configuracion/unidades', ['nombre' => 'Caja'])->assertUnauthorized();
    }

    public function test_usuario_de_lectura_no_puede_registrar_ni_cambiar_estados(): void
    {
        $this->administrador();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $unidad = UnidadInventario::factory()->create();

        $this->getJson('/api/v1/inventario/configuracion')->assertOk();
        $this->postJson('/api/v1/inventario/configuracion/unidades', ['nombre' => 'Caja'])->assertForbidden();
        $this->patchJson('/api/v1/inventario/configuracion/unidades/'.$unidad->id, ['activo' => false])->assertForbidden();
        $this->assertTrue($unidad->fresh()->activo);
    }

    public function test_usuario_sin_permisos_de_inventario_no_puede_consultar_catalogos(): void
    {
        $this->administrador();
        $permisos = DB::table('permisos')->whereIn('codigo', ['INVENTARIO_VER', 'INVENTARIO_GESTIONAR'])->pluck('id');
        DB::table('permiso_rol')->whereIn('permiso_id', $permisos)->delete();

        $this->getJson('/api/v1/inventario/configuracion')->assertForbidden();
        $this->getJson('/api/v1/inventario/opciones')->assertForbidden();
    }

    public function test_seeder_no_duplica_ni_reactiva_los_tipos_existentes(): void
    {
        $this->seed(ConfiguracionInventarioSeeder::class);
        TipoInventario::where('clave', 'PRODUCTO')->update(['activo' => false]);

        $this->seed(ConfiguracionInventarioSeeder::class);

        $this->assertDatabaseCount('tipos_inventario', 3);
        $this->assertDatabaseHas('tipos_inventario', ['clave' => 'PRODUCTO', 'activo' => false]);
    }

    public function test_rechaza_catalogos_y_registros_desconocidos(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/inventario/configuracion/desconocido', ['nombre' => 'Caja'])->assertNotFound();
        $this->patchJson('/api/v1/inventario/configuracion/unidades/00000000-0000-4000-8000-000000000000', ['activo' => false])->assertNotFound();
    }

    public function test_exige_nombre_tipo_y_estado_validos(): void
    {
        $this->administrador();

        $this->postJson('/api/v1/inventario/configuracion/tipos', [])->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'tipo_base']);
        $unidad = UnidadInventario::factory()->create();
        $this->patchJson('/api/v1/inventario/configuracion/unidades/'.$unidad->id, ['activo' => 'invalido'])->assertUnprocessable();
    }
}
