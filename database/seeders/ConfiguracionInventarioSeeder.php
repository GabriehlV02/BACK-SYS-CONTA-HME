<?php

namespace Database\Seeders;

use App\Models\TipoInventario;
use Illuminate\Database\Seeder;

class ConfiguracionInventarioSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Producto', 'PRODUCTO', 'Artículos destinados a la venta o distribución.'],
            ['Insumo', 'INSUMO', 'Materiales utilizados en la operación del hospital.'],
            ['Servicio', 'SERVICIO', 'Prestaciones y servicios ofrecidos por el hospital.'],
        ] as [$nombre, $clave, $descripcion]) {
            TipoInventario::firstOrCreate(['clave' => $clave], [
                'nombre' => $nombre, 'descripcion' => $descripcion, 'tipo_base' => $clave, 'activo' => true,
            ]);
        }
    }
}
