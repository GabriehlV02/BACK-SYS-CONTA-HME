<?php

namespace Database\Factories;

use App\Models\ProductoBase;
use App\Models\TipoInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductoBase> */
class ProductoBaseFactory extends Factory
{
    protected $model = ProductoBase::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->words(3, true);

        return ['nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'tipo_id' => TipoInventario::factory(), 'estado' => 'ACTIVO'];
    }
}
