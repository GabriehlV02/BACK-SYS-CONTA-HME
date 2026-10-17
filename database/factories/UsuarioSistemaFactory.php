<?php

namespace Database\Factories;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<UsuarioSistema> */
class UsuarioSistemaFactory extends Factory
{
    protected $model = UsuarioSistema::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tipo_usuario' => 'EMPRESA',
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'usuario' => fake()->unique()->userName(),
            'password' => 'Password-Prueba-2026',
            'estado' => 'ACTIVO',
        ];
    }

    public function conRol(string $codigo = 'ADMINISTRADOR'): static
    {
        return $this->afterCreating(function (UsuarioSistema $usuario) use ($codigo): void {
            $rolId = DB::table('roles')->where('codigo', $codigo)->soleValue('id');
            DB::table('rol_usuario')->insert(['usuario_id' => $usuario->id, 'rol_id' => $rolId]);
        });
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['estado' => 'INACTIVO']);
    }
}
