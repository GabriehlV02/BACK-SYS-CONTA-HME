<?php

namespace Database\Factories;

use App\Models\AlmacenInventario;
use App\Models\ExistenciaLote;
use App\Models\LoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExistenciaLote> */
class ExistenciaLoteFactory extends Factory
{
    protected $model = ExistenciaLote::class;

    public function definition(): array
    {
        return ['lote_id' => LoteItem::factory(), 'almacen_id' => AlmacenInventario::factory(), 'cantidad' => '10.000', 'costo_unitario' => '5.2500', 'fecha_ingreso' => '2026-10-20'];
    }
}
