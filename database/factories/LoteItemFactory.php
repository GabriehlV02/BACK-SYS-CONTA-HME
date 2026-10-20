<?php

namespace Database\Factories;

use App\Models\ItemInventario;
use App\Models\LoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoteItem> */
class LoteItemFactory extends Factory
{
    protected $model = LoteItem::class;

    public function definition(): array
    {
        $numero = fake()->unique()->bothify('LT-#####');

        return ['item_id' => ItemInventario::factory(), 'numero' => $numero, 'clave' => $numero, 'vencimiento' => '2028-01-01', 'precio_1' => '10.00', 'precio_2' => '12.00', 'precio_3' => '15.00'];
    }
}
