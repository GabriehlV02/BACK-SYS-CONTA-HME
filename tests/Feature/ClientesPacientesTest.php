<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Cliente;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientesPacientesTest extends TestCase
{
    use RefreshDatabase;

    private function preparar(): string
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        config(['services.clinico.url' => 'http://clinico.test', 'services.clinico.token' => str_repeat('x', 48)]);
        Http::preventStrayRequests();

        return '00000000-0000-4000-8000-000000000001';
    }

    public function test_vincula_cliente_existente_sin_duplicarlo_y_usa_datos_verificados(): void
    {
        $id = $this->preparar();
        $cliente = Cliente::factory()->create(['nit_ci' => 'VINC-123']);
        Http::fake(['clinico.test/api/integraciones/contable/pacientes*' => Http::response(['data' => [['id' => $id, 'ci' => 'VINC-123', 'nombres' => 'Paciente', 'apellido_paterno' => 'Clinico', 'apellido_materno' => 'Prueba', 'telefono' => '70000000']]])]);
        $this->postJson('/api/v1/clientes', ['paciente_id' => $id, 'nombre_razon_social' => 'Manipulado'])->assertCreated()
            ->assertJsonPath('data.id', $cliente->id)->assertJsonPath('data.paciente_id', $id)->assertJsonPath('data.nombre_razon_social', 'Paciente Clinico Prueba');
        $this->postJson('/api/v1/clientes', ['paciente_id' => $id])->assertCreated()->assertJsonPath('data.id', $cliente->id);
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'paciente_id' => $id]);
        Http::assertSentCount(2);
    }

    public function test_rechaza_paciente_inexistente_sin_crear_cliente(): void
    {
        $id = $this->preparar();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes*' => Http::response(['data' => []])]);
        $this->postJson('/api/v1/clientes', ['paciente_id' => $id])->assertUnprocessable();
        $this->assertDatabaseCount('clientes', 0);
        Http::assertSentCount(1);
    }

    public function test_recibe_la_identidad_desde_el_backend_clinico_sin_hacer_otra_consulta(): void
    {
        $id = $this->preparar();
        $datos = ['id' => $id, 'ci' => 'VINC-789', 'nombres' => 'Paciente', 'apellido_paterno' => 'Compartido', 'apellido_materno' => 'Prueba'];
        $this->postJson('/api/integraciones/clinico/clientes', $datos)->assertUnauthorized();
        $this->withToken(str_repeat('x', 48))->postJson('/api/integraciones/clinico/clientes', $datos)->assertCreated()->assertJsonPath('data.paciente_id', $id);
        $this->assertDatabaseHas('clientes', ['paciente_id' => $id, 'nit_ci' => 'VINC-789']);
        Http::assertNothingSent();
    }

    public function test_no_sustituye_el_vinculo_de_otro_paciente(): void
    {
        $id = $this->preparar();
        Cliente::factory()->create(['nit_ci' => 'VINC-123', 'paciente_id' => '00000000-0000-4000-8000-000000000002']);
        Http::fake(['clinico.test/api/integraciones/contable/pacientes*' => Http::response(['data' => [['id' => $id, 'ci' => 'VINC-123']]])]);
        $this->postJson('/api/v1/clientes', ['paciente_id' => $id])->assertUnprocessable();
        $this->assertDatabaseMissing('clientes', ['paciente_id' => $id]);
        Http::assertSentCount(1);
    }
}
