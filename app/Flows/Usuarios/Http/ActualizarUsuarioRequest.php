<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends RegistrarUsuarioRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['tipo_usuario'] = ['required', Rule::in(['EMPRESA'])];
        $rules['usuario'] = ['required', 'string', 'max:60', Rule::unique('usuarios_sistema', 'usuario')->ignore($this->route('usuario'))];
        $rules['password'] = ['nullable', 'string', 'min:8', 'max:255', 'confirmed'];

        return $rules;
    }
}
