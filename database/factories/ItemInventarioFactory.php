<?php

namespace Database\Factories;

use App\Models\ItemInventario;
use App\Models\ProductoBase;
use App\Models\UnidadInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemInventario> */
class ItemInventarioFactory extends Factory
{
    protected $model = ItemInventario::class;

    public function definition(): array
    {
        return [
            'producto_base_id' => ProductoBase::factory(), 'nombre_comercial' => fake()->unique()->words(3, true), 'marca' => fake()->company(), 'presentacion' => 'Tableta 500 mg', 'clave' => fake()->unique()->sha256(),
            'unidad_id' => UnidadInventario::factory(), 'precio_1' => '10.00', 'precio_2' => '12.00', 'precio_3' => '15.00', 'estado' => 'ACTIVO',
        ];
    }
}
