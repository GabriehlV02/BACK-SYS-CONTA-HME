<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistrarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contable')?->can('USUARIOS_CREAR') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['tipo_usuario' => $this->input('tipo_usuario', 'EMPRESA')]);
    }

    public function rules(): array
    {
        return [
            'tipo_usuario' => ['required', Rule::in(['EMPRESA', 'PACIENTE'])],
            'paciente_id' => ['nullable', 'uuid', 'required_if:tipo_usuario,PACIENTE'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['nullable', 'string', 'max:100'],
            'ci' => ['nullable', 'string', 'max:25'],
            'correo' => ['nullable', 'email', 'max:150'],
            'usuario' => ['required', 'string', 'max:60', Rule::unique('usuarios_sistema', 'usuario')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'rol_id' => ['required_if:tipo_usuario,EMPRESA', 'nullable', 'uuid', Rule::exists('roles', 'id')->where('activo', true)->whereNot('codigo', 'PACIENTE_PORTAL')],
            'estado' => ['sometimes', Rule::in(['ACTIVO', 'INACTIVO'])],
            'sucursal' => ['nullable', 'string', 'max:150'],
            'cajas' => ['sometimes', 'array', 'max:100'],
            'cajas.*' => ['required', 'string', 'max:100', 'distinct'],
            'almacenes' => ['sometimes', 'array', 'max:100'],
            'almacenes.*' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('rol_id') || ! $this->filled('rol_id')) {
                return;
            }
            if (! $this->user('contable')->puedeAsignarRol($this->input('rol_id'))) {
                $validator->errors()->add('rol_id', 'No puedes asignar un rol con permisos superiores a los tuyos.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'Ingresa los nombres.',
            'usuario.unique' => 'Ese usuario ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol_id.required_if' => 'Selecciona un rol para el usuario.',
            'rol_id.exists' => 'Selecciona un rol activo para usuarios del sistema.',
        ];
    }
}
