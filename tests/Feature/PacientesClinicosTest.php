<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesClinicosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
        config(['services.clinico.url' => 'http://clinico.test', 'services.clinico.token' => str_repeat('x', 48)]);
        Http::preventStrayRequests();
    }

    public function test_registra_en_el_clinico_sin_crear_paciente_ni_cuenta_local(): void
    {
        $this->administrador();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes' => Http::response(['data' => ['id' => 'paciente-compartido']], 201)]);
        $this->postJson('/api/v1/pacientes', ['nombres' => 'Prueba', 'ci' => 'TEST-123'])->assertCreated()->assertJsonPath('data.id', 'paciente-compartido');
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['ci'] === 'TEST-123' && $request->hasHeader('Authorization', 'Bearer '.str_repeat('x', 48)));
        $this->assertFalse(Schema::hasTable('pacientes'));
        $this->assertDatabaseCount('usuarios_sistema', 1);
    }

    public function test_consulta_y_pagina_los_pacientes_del_clinico(): void
    {
        $this->administrador();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes*' => Http::response(['data' => [['id' => 'compartido']], 'total' => 1, 'last_page' => 1])]);
        $this->getJson('/api/v1/pacientes?buscar=TEST&page=1')->assertOk()->assertJsonPath('data.0.id', 'compartido');
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request['buscar'] === 'TEST');
    }

    public function test_muestra_los_errores_de_validacion_clinica(): void
    {
        $this->administrador();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes' => Http::response(['errors' => ['ci' => ['Ya existe un paciente registrado con ese CI.']]], 422)]);
        $this->postJson('/api/v1/pacientes', ['ci' => 'TEST-123'])->assertUnprocessable()->assertJsonPath('errors.ci.0', 'Ya existe un paciente registrado con ese CI.');
        Http::assertSentCount(1);
    }

    public function test_no_reintenta_registro_si_falla_la_conexion(): void
    {
        $this->administrador();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes' => Http::failedConnection()]);
        $this->postJson('/api/v1/pacientes', ['ci' => 'TEST-123'])->assertStatus(503);
    }

    public function test_requiere_sesion_y_permiso_antes_de_contactar_al_clinico(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/v1/pacientes')->assertUnauthorized();
        $this->postJson('/api/v1/pacientes', [])->assertUnauthorized();
        $this->administrador();
        DB::table('permiso_rol')->delete();
        $this->getJson('/api/v1/pacientes')->assertForbidden();
        $this->postJson('/api/v1/pacientes', [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_no_revela_errores_internos_del_clinico(): void
    {
        $this->administrador();
        Http::fake(['clinico.test/api/integraciones/contable/pacientes*' => Http::response(['message' => 'SQL credentials'], 500)]);
        $this->getJson('/api/v1/pacientes')->assertStatus(503)->assertJsonPath('message', 'El sistema clínico no está disponible. Intenta nuevamente.');
        Http::assertSentCount(1);
    }
}
