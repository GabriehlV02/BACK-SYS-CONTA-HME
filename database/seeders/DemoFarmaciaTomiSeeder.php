<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoFarmaciaTomiSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Crea datos identificados como DEMO para probar TOMI sin alterar inventario real.
     */
    public function run(): void
    {
        $db = DB::connection();
        $ahora = now();

        $this->call(ConfiguracionInventarioSeeder::class);

        $unidad = $db->table('unidades_inventario')->where('clave', 'DEMO-UNIDAD')->first();
        if ($unidad) {
            $db->table('unidades_inventario')->where('id', $unidad->id)->update([
                'nombre' => 'Unidad (DEMO)', 'activo' => true, 'updated_at' => $ahora,
            ]);
        } else {
            $db->table('unidades_inventario')->insert([
                'id' => (string) Str::uuid(), 'nombre' => 'Unidad (DEMO)', 'clave' => 'DEMO-UNIDAD', 'activo' => true,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
        $unidadId = $db->table('unidades_inventario')->where('clave', 'DEMO-UNIDAD')->value('id');
        $tipoId = $db->table('tipos_inventario')->where('tipo_base', 'PRODUCTO')->value('id');

        $sucursalHospital = $this->upsertSucursal($db, 'DEMO-TOMI-H04', 'DEMO - Farmacia Hospital 04', $ahora);
        $sucursalNorte = $this->upsertSucursal($db, 'DEMO-TOMI-NORTE', 'DEMO - Farmacia Sucursal Norte', $ahora);
        $almacenHospital = $this->upsertAlmacen($db, $sucursalHospital, 'DEMO-TOMI-H04-ALM', 'DEMO - Almacén principal', $ahora);
        $almacenNorte = $this->upsertAlmacen($db, $sucursalNorte, 'DEMO-TOMI-NORTE-ALM', 'DEMO - Almacén general', $ahora);

        $bases = [
            'PARACETAMOL' => ['nombre' => 'Paracetamol', 'clave' => 'DEMO-TOMI-PARACETAMOL'],
            'IBUPROFENO' => ['nombre' => 'Ibuprofeno', 'clave' => 'DEMO-TOMI-IBUPROFENO'],
        ];
        foreach ($bases as $base) {
            $db->table('productos_base')->updateOrInsert(['clave' => $base['clave']], [
                'nombre' => $base['nombre'].' (DEMO)', 'tipo_id' => $tipoId,
                'descripcion' => 'Registro ficticio de demostración para TOMI.', 'estado' => 'ACTIVO',
                'updated_at' => $ahora, 'created_at' => $ahora,
            ]);
            $bases[$base['nombre']] = $db->table('productos_base')->where('clave', $base['clave'])->first();
        }

        $productos = [
            ['clave' => 'FAR002123', 'base' => $bases['Paracetamol']->id, 'nombre' => 'Paracetamol 500 mg', 'marca' => 'Bago', 'presentacion' => 'Tableta 500 mg'],
            ['clave' => 'FAR002124', 'base' => $bases['Paracetamol']->id, 'nombre' => 'Paracetamol 500 mg', 'marca' => 'HP Medical', 'presentacion' => 'Tableta 500 mg'],
            ['clave' => 'FAR002125', 'base' => $bases['Paracetamol']->id, 'nombre' => 'Paracetamol 500 mg', 'marca' => 'China Pharma', 'presentacion' => 'Tableta 500 mg'],
            ['clave' => 'FAR002126', 'base' => $bases['Ibuprofeno']->id, 'nombre' => 'Ibuprofeno 400 mg', 'marca' => 'DEMO Pharma', 'presentacion' => 'Tableta 400 mg'],
        ];
        foreach ($productos as &$producto) {
            $db->table('items_inventario')->updateOrInsert(['producto_base_id' => $producto['base'], 'clave' => $producto['clave']], [
                'nombre_comercial' => $producto['nombre'], 'marca' => $producto['marca'], 'presentacion' => $producto['presentacion'],
                'descripcion' => 'Producto ficticio para pruebas locales de TOMI.', 'unidad_id' => $unidadId,
                'precio_1' => 1, 'precio_2' => 1, 'precio_3' => 1, 'estado' => 'ACTIVO',
                'updated_at' => $ahora, 'created_at' => $ahora,
            ]);
            $producto['id'] = (int) $db->table('items_inventario')->where('producto_base_id', $producto['base'])->where('clave', $producto['clave'])->value('id');
        }
        unset($producto);

        $cantidades = [
            'FAR002123' => [['clave' => 'DEMO-BAGO-2027', 'numero' => 'DEMO-BAGO-2027', 'meses' => 12, 'cantidad' => 3]],
            'FAR002124' => [['clave' => 'DEMO-HP-2027', 'numero' => 'DEMO-HP-2027', 'meses' => 8, 'cantidad' => 0]],
            'FAR002125' => [
                ['clave' => 'DEMO-CHINA-LOTE-A', 'numero' => 'DEMO-CHINA-LOTE-A', 'meses' => 2, 'cantidad' => 30],
                ['clave' => 'DEMO-CHINA-LOTE-B', 'numero' => 'DEMO-CHINA-LOTE-B', 'meses' => 5, 'cantidad' => 20],
            ],
            'FAR002126' => [['clave' => 'DEMO-IBU-2027', 'numero' => 'DEMO-IBU-2027', 'meses' => 10, 'cantidad' => 12]],
        ];
        foreach ($productos as $producto) {
            foreach ($cantidades[$producto['clave']] as $lote) {
                $db->table('lotes_items')->updateOrInsert(['item_id' => $producto['id'], 'clave' => $lote['clave']], [
                    'numero' => $lote['numero'], 'vencimiento' => $ahora->copy()->addMonths($lote['meses'])->toDateString(),
                    'precio_1' => 1, 'precio_2' => 1, 'precio_3' => 1, 'updated_at' => $ahora, 'created_at' => $ahora,
                ]);
                $loteId = (int) $db->table('lotes_items')->where('item_id', $producto['id'])->where('clave', $lote['clave'])->value('id');
                $stockHospital = $lote['cantidad'];
                $db->table('existencias_lote')->updateOrInsert(['lote_id' => $loteId, 'almacen_id' => $almacenHospital], [
                    'cantidad' => $stockHospital, 'costo_unitario' => 1, 'fecha_ingreso' => $ahora->toDateString(),
                    'updated_at' => $ahora, 'created_at' => $ahora,
                ]);
                $stockNorte = $producto['clave'] === 'FAR002125' && $lote['clave'] === 'DEMO-CHINA-LOTE-A' ? 6
                    : ($producto['clave'] === 'FAR002126' ? 5 : 0);
                $db->table('existencias_lote')->updateOrInsert(['lote_id' => $loteId, 'almacen_id' => $almacenNorte], [
                    'cantidad' => $stockNorte, 'costo_unitario' => 1, 'fecha_ingreso' => $ahora->toDateString(),
                    'updated_at' => $ahora, 'created_at' => $ahora,
                ]);
            }
        }

        $repisasHospital = $this->crearEstructuras($db, $almacenHospital, 20, $ahora);
        $this->crearEstructuras($db, $almacenNorte, 4, $ahora);
        foreach (['FAR002123' => 2, 'FAR002124' => 8, 'FAR002125' => 20, 'FAR002126' => 4] as $codigo => $estante) {
            $producto = collect($productos)->firstWhere('clave', $codigo);
            $repisa = $repisasHospital[$estante];
            $db->table('item_almacen_ubicaciones')->updateOrInsert(['item_id' => $producto['id'], 'almacen_id' => $almacenHospital], [
                'ubicacion' => "Estante {$estante} / Repisa 2", 'repisa_id' => $repisa,
                'updated_at' => $ahora, 'created_at' => $ahora,
            ]);
        }
        $this->crearInventarioHistorico($db, $sucursalHospital, $almacenHospital, $productos, [3, 0, 50, 12], [2, 0, 48, 12], '00000000-0000-4000-8000-000000000004', $ahora);
        $this->crearInventarioHistorico($db, $sucursalNorte, $almacenNorte, $productos, [0, 0, 6, 5], [0, 0, 6, 7], '00000000-0000-4000-8000-000000000005', $ahora);

        $this->command?->info('Datos DEMO de TOMI listos: 2 sucursales, catálogo de prueba, lotes, ubicaciones y 2 inventarios históricos.');
    }

    private function upsertSucursal($db, string $codigo, string $nombre, $ahora): int
    {
        $db->table('sucursales_inventario')->updateOrInsert(['codigo' => $codigo], [
            'nombre' => $nombre, 'activo' => true, 'updated_at' => $ahora, 'created_at' => $ahora,
        ]);

        return (int) $db->table('sucursales_inventario')->where('codigo', $codigo)->value('id');
    }

    private function upsertAlmacen($db, int $sucursalId, string $codigo, string $nombre, $ahora): int
    {
        $db->table('almacenes_inventario')->updateOrInsert(['codigo' => $codigo], [
            'sucursal_id' => $sucursalId, 'nombre' => $nombre, 'activo' => true, 'updated_at' => $ahora, 'created_at' => $ahora,
        ]);

        return (int) $db->table('almacenes_inventario')->where('codigo', $codigo)->value('id');
    }

    private function crearEstructuras($db, int $almacenId, int $cantidadEstantes, $ahora): array
    {
        $repisasEstantes = [];
        for ($numero = 1; $numero <= $cantidadEstantes; $numero++) {
            $nombre = "Estante {$numero}";
            $db->table('farmacia_espacios')->updateOrInsert(['almacen_id' => $almacenId, 'tipo' => 'ESTANTE', 'nombre' => $nombre], [
                'updated_at' => $ahora, 'created_at' => $ahora,
            ]);
            $espacioId = (int) $db->table('farmacia_espacios')->where('almacen_id', $almacenId)->where('tipo', 'ESTANTE')->where('nombre', $nombre)->value('id');
            for ($nivel = 1; $nivel <= 4; $nivel++) {
                $nombreRepisa = "Repisa {$nivel}";
                $db->table('farmacia_repisas')->updateOrInsert(['espacio_id' => $espacioId, 'nombre' => $nombreRepisa], [
                    'orden' => $nivel, 'updated_at' => $ahora, 'created_at' => $ahora,
                ]);
                if ($nivel === 2) {
                    $repisasEstantes[$numero] = (int) $db->table('farmacia_repisas')->where('espacio_id', $espacioId)->where('nombre', $nombreRepisa)->value('id');
                }
            }
        }

        foreach ([['FRIGOBAR', 'Frigobar de demostración', 3], ['MOSTRADOR', 'Mostrador de dispensación', 2], ['OTRO', 'Zona de devoluciones', 1]] as [$tipo, $nombre, $cantidadRepisas]) {
            $db->table('farmacia_espacios')->updateOrInsert(['almacen_id' => $almacenId, 'tipo' => $tipo, 'nombre' => $nombre], [
                'updated_at' => $ahora, 'created_at' => $ahora,
            ]);
            $espacioId = (int) $db->table('farmacia_espacios')->where('almacen_id', $almacenId)->where('tipo', $tipo)->where('nombre', $nombre)->value('id');
            for ($nivel = 1; $nivel <= $cantidadRepisas; $nivel++) {
                $nombreRepisa = "Repisa {$nivel}";
                $db->table('farmacia_repisas')->updateOrInsert(['espacio_id' => $espacioId, 'nombre' => $nombreRepisa], [
                    'orden' => $nivel, 'updated_at' => $ahora, 'created_at' => $ahora,
                ]);
            }
        }

        return $repisasEstantes;
    }

    private function crearInventarioHistorico($db, int $sucursalId, int $almacenId, array $productos, array $esperadas, array $contadas, string $marcaDemo, $ahora): void
    {
        $iniciado = $ahora->copy()->subDays(2);
        $finalizado = $iniciado->copy()->addMinutes(35);
        $db->table('farmacia_inventarios')->updateOrInsert(['iniciado_por' => $marcaDemo, 'almacen_id' => $almacenId], [
            'sucursal_id' => $sucursalId, 'estado' => 'FINALIZADO', 'iniciado_en' => $iniciado,
            'finalizado_en' => $finalizado, 'updated_at' => $ahora, 'created_at' => $iniciado,
        ]);
        $inventarioId = (int) $db->table('farmacia_inventarios')->where('iniciado_por', $marcaDemo)->where('almacen_id', $almacenId)->value('id');
        foreach ($productos as $indice => $producto) {
            $db->table('farmacia_inventario_detalles')->updateOrInsert(['inventario_id' => $inventarioId, 'item_id' => $producto['id']], [
                'cantidad_esperada' => $esperadas[$indice], 'cantidad_contada' => $contadas[$indice],
                'contado_en' => $finalizado, 'updated_at' => $ahora, 'created_at' => $iniciado,
            ]);
        }
    }
}
