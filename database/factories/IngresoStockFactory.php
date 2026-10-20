<?php

namespace Database\Factories;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\AlmacenInventario;
use App\Models\IngresoStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IngresoStock> */
class IngresoStockFactory extends Factory
{
    protected $model = IngresoStock::class;

    public function definition(): array
    {
        return ['solicitud_id' => fake()->uuid(), 'huella' => fake()->sha256(), 'fecha' => '2026-10-20',
            'proveedor' => fake()->company(), 'comprobante' => fake()->bothify('FAC-####'),
            'almacen_id' => AlmacenInventario::factory(), 'almacen_nombre' => 'Casa matriz - Farmacia',
            'usuario_id' => UsuarioSistema::factory(), 'total' => '0.00'];
    }
}
