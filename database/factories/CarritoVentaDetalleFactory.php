<?php

namespace Database\Factories;

use App\Models\CarritoVenta;
use App\Models\CarritoVentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarritoVentaDetalle>
 */
class CarritoVentaDetalleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'carrito_venta_id' => CarritoVenta::factory(),
            'item_id' => null,
            'codigo_item' => fake()->bothify('SRV-###'),
            'nombre_item' => fake()->words(3, true),
            'tipo_item' => 'SERVICIO',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'descuento' => 0,
            'total' => 100,
        ];
    }
}
