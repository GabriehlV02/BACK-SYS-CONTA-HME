<?php

namespace Database\Factories;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Dispositivo;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispositivo>
 */
class DispositivoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Terminal '.fake()->word(), 'codigo' => fake()->unique()->bothify('DIS-#####'),
            'tipo' => 'Computadora', 'sucursal_id' => SucursalInventario::factory(), 'usuario_id' => UsuarioSistema::factory(),
            'area' => 'Recepción', 'uso' => 'Institucional', 'activo' => true,
        ];
    }
}
