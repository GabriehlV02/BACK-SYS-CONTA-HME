<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => null,
            'nit_ci' => fake()->unique()->numerify('########'),
            'nombre_razon_social' => fake()->name(),
            'telefono' => '+591 '.fake()->numerify('########'),
            'correo' => fake()->safeEmail(),
            'activo' => true,
        ];
    }
}
