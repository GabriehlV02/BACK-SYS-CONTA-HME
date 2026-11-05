<?php

namespace Tests\Feature;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\CarritoVenta;
use App\Models\CarritoVentaDetalle;
use App\Models\Cliente;
use Database\Seeders\GestionAccesosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VentaDirectaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_de_cliente_para_venta_no_crea_paciente_clinico(): void
    {
        $this->autenticarAdministrador();
        $respuesta = $this->postJson('/api/v1/clientes', [
            'nit_ci' => '9382286',
            'nombre_razon_social' => 'Cliente de mostrador',
            'telefono' => '+591 70000000',
            'correo' => 'cliente@example.test',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.nit_ci', '9382286')
            ->assertJsonMissingPath('data.paciente_id');

        $this->assertDatabaseHas('clientes', ['nit_ci' => '9382286']);
    }

    public function test_busca_clientes_por_nombre_ci_telefono_o_correo(): void
    {
        $this->autenticarAdministrador();
        Cliente::factory()->create([
            'nit_ci' => '9382286',
            'nombre_razon_social' => 'Cliente de mostrador',
            'telefono' => '70000000',
            'correo' => 'cliente@example.test',
        ]);

        $this->getJson('/api/v1/clientes?buscar=9382286')
            ->assertOk()
            ->assertJsonPath('data.0.nombre_razon_social', 'Cliente de mostrador');
    }

    public function test_registra_venta_directa_con_cliente_y_detalles_sin_abrir_cuenta(): void
    {
        $cliente = Cliente::factory()->create([
            'paciente_id' => (string) Str::uuid(),
            'nit_ci' => '9382286',
            'nombre_razon_social' => 'Paciente de prueba',
        ]);
        $venta = CarritoVenta::factory()->create([
            'cliente_id' => $cliente->id,
            'subtotal' => 120,
            'total' => 120,
        ]);
        CarritoVentaDetalle::factory()->create([
            'carrito_venta_id' => $venta->id,
            'nombre_item' => 'Radiografía de tórax',
            'precio_unitario' => 120,
            'total' => 120,
        ]);

        $this->assertSame($cliente->id, $venta->fresh()->cliente->id);
        $this->assertCount(1, $venta->detalles);
        $this->assertSame('Radiografía de tórax', $venta->detalles->first()->nombre_item);
        $this->assertSame('COMPLETADA', $venta->estado);
    }

    public function test_registra_una_venta_desde_la_api_con_codigo_correlativo(): void
    {
        $this->autenticarAdministrador();
        $cliente = Cliente::factory()->create();

        $respuesta = $this->postJson('/api/v1/ventas', [
            'cliente_id' => $cliente->id,
            'descuento' => 10,
            'detalles' => [
                [
                    'codigo_item' => 'SRV-001',
                    'nombre_item' => 'Consulta medica general',
                    'tipo_item' => 'Servicio',
                    'cantidad' => 2,
                    'precio_unitario' => 80,
                ],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.codigo', 'VTA-00000001')
            ->assertJsonPath('data.estado', 'COMPLETADA')
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonPath('data.cliente.id', $cliente->id);

        $this->assertDatabaseHas('carritos_venta', [
            'cliente_id' => $cliente->id,
            'codigo' => 'VTA-00000001',
            'total' => 150,
        ]);
    }

    public function test_lista_ventas_y_permite_filtrar_por_fechas(): void
    {
        $this->autenticarAdministrador();
        $cliente = Cliente::factory()->create(['nombre_razon_social' => 'Cliente visible']);
        CarritoVenta::factory()->create([
            'cliente_id' => $cliente->id,
            'codigo' => 'VTA-00000001',
            'total' => 120,
            'cobrada_en' => '2026-10-15 09:30:00',
        ]);
        CarritoVenta::factory()->create([
            'cliente_id' => $cliente->id,
            'codigo' => 'VTA-00000002',
            'cobrada_en' => '2026-10-14 18:00:00',
        ]);

        $this->getJson('/api/v1/ventas?fecha_desde=2026-10-15&fecha_hasta=2026-10-15')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'VTA-00000001')
            ->assertJsonPath('data.0.cliente.nombre_razon_social', 'Cliente visible');
    }

    public function test_lista_pagos_clinicos_y_deja_sin_registro_los_pagos_desconocidos(): void
    {
        $this->autenticarAdministrador();
        $venta = CarritoVenta::factory()->create(['cobrada_en' => '2026-10-15 09:30:00']);
        $sinPago = CarritoVenta::factory()->create(['cobrada_en' => '2026-10-15 08:30:00']);
        DB::table('ventas_clinicas_solicitudes')->insert([
            'solicitud_id' => (string) Str::uuid(), 'usuario_id' => auth('contable')->id(), 'venta_id' => $venta->id,
            'huella' => str_repeat('a', 64), 'pagos' => json_encode([['metodo' => 'Efectivo', 'monto' => '20.00']], JSON_THROW_ON_ERROR),
            'cambio' => '2.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/ventas?fecha_desde=2026-10-15&fecha_hasta=2026-10-15')
            ->assertOk()->assertJsonPath('data.0.id', $venta->id)
            ->assertJsonPath('data.0.pagos.0.metodo', 'Efectivo')
            ->assertJsonPath('data.1.id', $sinPago->id)
            ->assertJsonPath('data.1.pagos', null);
    }

    private function autenticarAdministrador(): void
    {
        $this->seed(GestionAccesosSeeder::class);
        $this->actingAs(UsuarioSistema::factory()->conRol()->create(), 'contable');
    }
}
