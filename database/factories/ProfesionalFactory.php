<?php

namespace Database\Factories;

use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profesional>
 */
class ProfesionalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(), 'apellidos' => fake()->lastName(),
            'ci' => fake()->unique()->numerify('#######'), 'tipo' => 'Médico/a',
            'especialidades' => ['Medicina general'], 'rol' => 'Médico tratante',
            'matricula' => fake()->unique()->bothify('MED-######'), 'telefono' => '70000000', 'activo' => true,
        ];
    }
}
