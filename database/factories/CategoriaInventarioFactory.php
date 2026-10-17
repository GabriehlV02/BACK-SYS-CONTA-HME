<?php

namespace Database\Factories;

use App\Models\CategoriaInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CategoriaInventario> */
class CategoriaInventarioFactory extends Factory
{
    protected $model = CategoriaInventario::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->word();

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'activo' => true,
            'prefijo' => Str::upper(fake()->unique()->lexify('???')),
        ];
    }
}
