<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Foundation\Http\FormRequest;

class IniciarSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'usuario.required' => 'Ingresa tu usuario.',
            'usuario.string' => 'El usuario debe ser texto.',
            'usuario.max' => 'El usuario no debe superar los 60 caracteres.',
            'password.required' => 'Ingresa tu contraseña.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.max' => 'La contraseña no debe superar los 255 caracteres.',
        ];
    }
}
