<?php

namespace Database\Factories;

use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SucursalInventario> */
class SucursalInventarioFactory extends Factory
{
    protected $model = SucursalInventario::class;

    public function definition(): array
    {
        return ['nombre' => fake()->company(), 'codigo' => fake()->unique()->bothify('SUC-#####'), 'activo' => true];
    }
}
