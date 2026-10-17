<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarPacienteUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contable')?->can('USUARIOS_CREAR') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'max:25', Rule::unique('usuarios_sistema', 'ci')->where('tipo_usuario', 'PACIENTE')],
            'usuario' => ['required', 'string', 'max:60', Rule::unique('usuarios_sistema', 'usuario')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'tipo_usuario' => ['prohibited'],
            'rol_id' => ['prohibited'],
            'rol' => ['prohibited'],
            'permisos' => ['prohibited'],
            'estado' => ['prohibited'],
            'paciente_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre completo del paciente.',
            'nombre.max' => 'El nombre completo no debe superar los 100 caracteres.',
            'ci.required' => 'Ingresa el CI del paciente.',
            'ci.max' => 'El CI no debe superar los 25 caracteres.',
            'ci.unique' => 'Este paciente ya tiene una cuenta registrada con ese CI.',
            'usuario.required' => 'Ingresa el usuario.',
            'usuario.max' => 'El usuario no debe superar los 60 caracteres.',
            'usuario.unique' => 'Ese usuario ya está registrado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no debe superar los 255 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
