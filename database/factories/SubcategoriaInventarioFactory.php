<?php

namespace Database\Factories;

use App\Models\CategoriaInventario;
use App\Models\SubcategoriaInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SubcategoriaInventario> */
class SubcategoriaInventarioFactory extends Factory
{
    protected $model = SubcategoriaInventario::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->word();

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'activo' => true,
            'categoria_id' => CategoriaInventario::factory(),
        ];
    }
}
