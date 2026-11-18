<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CuentasEmergenciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_cuentas_clinicas_con_credencial_interna(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        config(['services.clinico.url' => 'http://clinico.test', 'services.clinico.token' => str_repeat('x', 48)]);
        Http::preventStrayRequests();
        Http::fake(['clinico.test/api/integraciones/contable/cuentas-emergencias' => Http::response(['data' => [['id' => 'cuenta-1', 'total' => 50, 'detalles' => [['concepto' => 'Laboratorio']]]]])]);
        $this->getJson('/api/v1/cuentas-emergencias')->assertOk()->assertJsonPath('data.0.total', 50)->assertJsonPath('data.0.detalles.0.concepto', 'Laboratorio');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.str_repeat('x', 48)));
    }

    public function test_requiere_permiso_y_no_oculta_la_indisponibilidad(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/v1/cuentas-emergencias')->assertUnauthorized();
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        config(['services.clinico.url' => 'http://clinico.test', 'services.clinico.token' => str_repeat('x', 48)]);
        DB::table('permiso_rol')->delete();
        $this->getJson('/api/v1/cuentas-emergencias')->assertForbidden();
        Http::assertNothingSent();
    }
}
