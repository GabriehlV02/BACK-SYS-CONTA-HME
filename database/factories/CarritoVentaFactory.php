<?php

namespace Database\Factories;

use App\Models\CarritoVenta;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarritoVenta>
 */
class CarritoVentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('VTA-########'),
            'cliente_id' => Cliente::factory(),
            'usuario_sistema_id' => null,
            'estado' => 'COMPLETADA',
            'moneda' => 'BOB',
            'subtotal' => 0,
            'descuento' => 0,
            'total' => 0,
            'observaciones' => null,
            'cobrada_en' => now(),
            'anulada_en' => null,
        ];
    }
}
