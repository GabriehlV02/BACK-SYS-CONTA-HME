<?php

namespace Database\Factories;

use App\Models\Caja;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Caja> */
class CajaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->words(2, true), 'codigo' => fake()->unique()->bothify('CAJ-########'),
            'sucursal_id' => SucursalInventario::factory(), 'tipos_item' => ['Productos'], 'activo' => true];
    }
}
