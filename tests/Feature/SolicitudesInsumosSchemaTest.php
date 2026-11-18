<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolicitudesInsumosSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_migracion_crea_los_campos_utilizados_por_enfermeria(): void
    {
        $this->assertTrue(Schema::hasTable('clinico_solicitudes_insumos'));
        $this->assertTrue(Schema::hasColumns('clinico_solicitudes_insumos', [
            'id', 'solicitado_por', 'solicitante', 'almacen_destino_id', 'estado', 'detalles', 'observacion',
            'almacen_origen_id', 'dispensado_por', 'dispensado_en', 'movimientos', 'created_at', 'updated_at',
        ]));
    }

    public function test_la_migracion_crea_el_registro_de_consumos_y_recuperacion(): void
    {
        $this->assertTrue(Schema::hasTable('clinico_consumos_inventario'));
        $this->assertTrue(Schema::hasColumns('clinico_consumos_inventario', [
            'id', 'emergencia_id', 'usuario_id', 'almacen_id', 'item_id', 'cantidad', 'huella',
            'datos_clinicos', 'lotes', 'sincronizado_en', 'created_at', 'updated_at',
        ]));
    }
}
