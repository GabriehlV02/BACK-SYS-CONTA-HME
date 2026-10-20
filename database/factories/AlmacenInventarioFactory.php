<?php

namespace Database\Factories;

use App\Models\AlmacenInventario;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AlmacenInventario> */
class AlmacenInventarioFactory extends Factory
{
    protected $model = AlmacenInventario::class;

    public function definition(): array
    {
        return ['nombre' => fake()->company(), 'codigo' => fake()->unique()->bothify('ALM-#####'), 'sucursal_id' => SucursalInventario::factory(), 'activo' => true];
    }
}
