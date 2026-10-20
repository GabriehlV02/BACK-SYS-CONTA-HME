<?php

namespace Database\Factories;

use App\Models\CategoriaInventario;
use App\Models\Servicio;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use App\Models\UnidadInventario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Servicio> */
class ServicioFactory extends Factory
{
    protected $model = Servicio::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->words(3, true);

        return [
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)),
            'descripcion' => fake()->sentence(), 'precio_venta' => '50.00', 'estado' => 'ACTIVO',
            'tipo_id' => TipoInventario::factory()->state(['tipo_base' => 'SERVICIO']),
            'categoria_id' => CategoriaInventario::factory(),
            'subcategoria_id' => fn (array $attributes): string => SubcategoriaInventario::factory()->create(['categoria_id' => $attributes['categoria_id']])->id,
            'unidad_id' => UnidadInventario::factory(),
        ];
    }
}
