<?php

namespace Database\Factories;

use App\Models\UnidadInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<UnidadInventario> */
class UnidadInventarioFactory extends Factory
{
    protected $model = UnidadInventario::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->word();

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'activo' => true,
        ];
    }
}
