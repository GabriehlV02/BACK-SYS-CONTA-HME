<?php

namespace Database\Factories;

use App\Models\ClasificacionInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ClasificacionInventario> */
class ClasificacionInventarioFactory extends Factory
{
    protected $model = ClasificacionInventario::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->word();

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'activo' => true,
        ];
    }
}
