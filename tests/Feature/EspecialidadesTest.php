<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\EspecialidadesSeeder;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EspecialidadesTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_se_protege_y_la_carga_inicial_no_duplica_registros(): void
    {
        $this->getJson('/api/v1/especialidades')->assertUnauthorized();
        $this->seed(GestionAccesosSeeder::class);
        $this->seed(EspecialidadesSeeder::class);
        $cantidad = DB::table('especialidades')->count();
        $this->seed(EspecialidadesSeeder::class);
        $this->assertSame($cantidad, DB::table('especialidades')->count());
        $this->assertGreaterThan(70, $cantidad);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        $this->getJson('/api/v1/especialidades')->assertOk()->assertJsonFragment(['nombre' => 'Cardiología']);
    }

    public function test_administrador_crea_desactiva_y_rechaza_duplicados(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        $id = $this->postJson('/api/v1/especialidades', ['nombre' => '  Cardiología  '])->assertCreated()->assertJsonPath('data.nombre', 'Cardiología')->json('data.id');
        $this->postJson('/api/v1/especialidades', ['nombre' => 'cardiología'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->patchJson('/api/v1/especialidades/'.$id, ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertDatabaseHas('especialidades', ['id' => $id, 'activo' => false]);
    }

    public function test_usuario_sin_permiso_no_modifica_el_catalogo(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        DB::table('permiso_rol')->delete();
        $this->postJson('/api/v1/especialidades', ['nombre' => 'Cardiología'])->assertForbidden();
        $this->assertDatabaseCount('especialidades', 0);
    }
}
