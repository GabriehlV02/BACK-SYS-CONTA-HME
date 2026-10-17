<?php

namespace Database\Factories;

use App\Models\TipoInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TipoInventario> */
class TipoInventarioFactory extends Factory
{
    protected $model = TipoInventario::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->word();

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)), 'activo' => true,
            'tipo_base' => 'PRODUCTO',
        ];
    }
}
