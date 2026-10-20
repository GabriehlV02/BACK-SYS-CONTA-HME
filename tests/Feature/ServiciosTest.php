<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\CategoriaInventario;
use App\Models\ClasificacionInventario;
use App\Models\Servicio;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use App\Models\UnidadInventario;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiciosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }

    private function datos(): array
    {
        $categoria = CategoriaInventario::factory()->create();
        $subcategoria = SubcategoriaInventario::factory()->create(['categoria_id' => $categoria->id]);
        $tipo = TipoInventario::factory()->create(['tipo_base' => 'SERVICIO']);
        $unidad = UnidadInventario::factory()->create();

        return ['nombre' => 'Consulta médica general', 'descripcion' => 'Atención médica ambulatoria', 'tipoOpcion' => $tipo->clave,
            'tipo' => 'SERVICIO', 'categoria' => $categoria->clave, 'grupo' => $subcategoria->clave,
            'unidadMedida' => $unidad->clave, 'precioVenta' => '50.25', 'estado' => 'ACTIVO', 'clasificacion' => '',
            'usarNumeroSerie' => false, 'imagen' => ''];
    }

    public function test_registra_lista_y_cuenta_servicios_con_precio_exacto(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/inventario/servicios', $datos)->assertCreated()
            ->assertJsonPath('data.precioVenta', '50.25')->assertJsonPath('data.tipo', 'Servicio')->assertJsonPath('data.stock', 0);
        $id = $respuesta->json('data.id');
        $respuesta->assertJsonPath('data.codigo', 'SRV-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT));
        $this->assertDatabaseHas('servicios', ['id' => $id, 'nombre' => $datos['nombre'], 'precio_venta' => '50.25']);
        $this->getJson('/api/v1/inventario/servicios')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/inventario/configuracion')->assertOk()->assertJsonPath('data.tipos.0.cantidad', 1);
    }

    public function test_actualiza_servicio_conservando_codigo_y_puede_desactivarlo(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $respuesta = $this->postJson('/api/v1/inventario/servicios', $datos)->assertCreated();
        $datos['estado'] = 'INACTIVO';
        $datos['precioVenta'] = '75.50';
        $this->putJson('/api/v1/inventario/servicios/'.$respuesta->json('data.id'), $datos)->assertOk()
            ->assertJsonPath('data.codigo', $respuesta->json('data.codigo'))->assertJsonPath('data.precioVenta', '75.50');
        $this->assertDatabaseHas('servicios', ['id' => $respuesta->json('data.id'), 'estado' => 'INACTIVO', 'precio_venta' => '75.50']);
    }

    public function test_rechaza_duplicado_ignorando_tildes_y_espacios(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertCreated();
        $datos['nombre'] = '  CONSULTA MEDICA GENERAL  ';
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertUnprocessable()->assertJsonValidationErrors('clave');
        $this->assertDatabaseCount('servicios', 1);
    }

    public static function preciosInvalidos(): array
    {
        return [['-1'], ['10.123'], ['10000000000'], ['abc']];
    }

    #[DataProvider('preciosInvalidos')]
    public function test_rechaza_precio_invalido(string $precio): void
    {
        $this->administrador();
        $this->postJson('/api/v1/inventario/servicios', [...$this->datos(), 'precioVenta' => $precio])
            ->assertUnprocessable()->assertJsonValidationErrors('precio_venta');
        $this->assertDatabaseCount('servicios', 0);
    }

    public function test_exige_tipo_servicio_y_catalogos_activos(): void
    {
        $this->administrador();
        $datos = $this->datos();
        TipoInventario::where('clave', $datos['tipoOpcion'])->update(['tipo_base' => 'PRODUCTO']);
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
        TipoInventario::where('clave', $datos['tipoOpcion'])->update(['tipo_base' => 'SERVICIO', 'activo' => false]);
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
        TipoInventario::where('clave', $datos['tipoOpcion'])->update(['activo' => true]);
        UnidadInventario::where('clave', $datos['unidadMedida'])->update(['activo' => false]);
        CategoriaInventario::where('clave', $datos['categoria'])->update(['activo' => false]);
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertUnprocessable()->assertJsonValidationErrors(['unidad_id', 'categoria_id']);
    }

    public function test_no_acepta_subcategoria_de_otra_categoria_ni_relaciones_inyectadas(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $otra = SubcategoriaInventario::factory()->create();
        $datos['grupo'] = $otra->clave;
        $datos['subcategoria_id'] = $otra->id;
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertUnprocessable()->assertJsonValidationErrors('subcategoria_id');
    }

    public function test_guarda_clasificacion_y_codigo_opcional_y_rechaza_imagen_externa(): void
    {
        $this->administrador();
        $datos = $this->datos();
        $datos['clasificacion'] = ClasificacionInventario::factory()->create()->clave;
        $datos['usarNumeroSerie'] = true;
        $datos['numeroSerie'] = 'LAB-01';
        $respuesta = $this->postJson('/api/v1/inventario/servicios', $datos)->assertCreated()
            ->assertJsonPath('data.numeroSerie', 'LAB-01')->assertJsonPath('data.clasificacion', $datos['clasificacion']);
        $datos['imagen'] = 'https://externo.test/imagen.svg';
        $this->putJson('/api/v1/inventario/servicios/'.$respuesta->json('data.id'), $datos)->assertUnprocessable()->assertJsonValidationErrors('imagen');
    }

    public function test_exige_permisos_y_sesion_en_las_rutas(): void
    {
        $this->getJson('/api/v1/inventario/servicios')->assertUnauthorized();
        $this->postJson('/api/v1/inventario/servicios', [])->assertUnauthorized();
        $this->administrador();
        $datos = $this->datos();
        $servicio = Servicio::factory()->create();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_GESTIONAR')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/servicios')->assertOk();
        $this->postJson('/api/v1/inventario/servicios', $datos)->assertForbidden();
        $this->putJson('/api/v1/inventario/servicios/'.$servicio->id, $datos)->assertForbidden();
        $permiso = DB::table('permisos')->where('codigo', 'INVENTARIO_VER')->value('id');
        DB::table('permiso_rol')->where('permiso_id', $permiso)->delete();
        $this->getJson('/api/v1/inventario/servicios')->assertForbidden();
    }

    public function test_lista_vacia_no_inventa_servicios_y_exige_campos_obligatorios(): void
    {
        $this->administrador();
        $this->getJson('/api/v1/inventario/servicios')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/inventario/servicios', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'descripcion', 'tipo_id', 'categoria_id', 'subcategoria_id', 'unidad_id', 'precio_venta', 'estado']);
    }
}
