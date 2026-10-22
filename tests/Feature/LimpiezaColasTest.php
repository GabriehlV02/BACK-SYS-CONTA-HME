<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LimpiezaColasTest extends TestCase
{
    use RefreshDatabase;

    public function test_conserva_sesiones_cache_y_dominio_sin_tablas_de_colas(): void
    {
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla));
        }
        foreach (['sessions', 'cache', 'cache_locks', 'usuarios_sistema', 'items_inventario', 'servicios', 'existencias_lote'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla));
        }
    }

    public function test_no_descarta_trabajos_pendientes_y_permita_reversion_del_esquema(): void
    {
        $migracion = require database_path('migrations/2026_10_21_204410_remove_unused_queue_tables.php');
        $migracion->down();
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => 0, 'created_at' => 0]);
        try {
            $migracion->up();
            $this->fail('Debe conservar los trabajos pendientes.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No se elimina jobs', $e->getMessage());
        }
        $this->assertDatabaseCount('jobs', 1);
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        DB::table('jobs')->delete();
        $migracion->up();
        $this->assertFalse(Schema::hasTable('jobs'));
    }

    public function test_rechaza_limpiar_colas_configuradas_en_base_de_datos(): void
    {
        config(['queue.default' => 'database']);
        $migracion = require database_path('migrations/2026_10_21_204410_remove_unused_queue_tables.php');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QUEUE_CONNECTION=sync');
        $migracion->up();
    }
}
