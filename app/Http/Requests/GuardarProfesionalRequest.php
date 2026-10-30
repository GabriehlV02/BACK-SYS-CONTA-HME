<?php

namespace App\Http\Requests;

use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Profesional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarProfesionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contable')?->can('ADMINISTRAR_CATALOGO') && $this->user('contable')?->can('INVENTARIO_GESTIONAR');
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'uuid', Rule::exists('usuarios_sistema', 'id')->where('tipo_usuario', 'EMPRESA'), Rule::unique('profesionales')->ignore($this->route('profesional'))],
            'tipo' => ['required', Rule::in(Profesional::TIPOS)],
            'especialidades' => ['sometimes', 'array', 'max:20'],
            'especialidades.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'rol' => ['nullable', 'string', 'max:100'],
            'matricula' => ['nullable', 'string', 'max:60', Rule::unique('profesionales')->ignore($this->route('profesional'))],
            'telefono' => ['nullable', 'string', 'max:30'], 'activo' => ['sometimes', 'boolean'],
            'cuenta' => ['prohibited'], 'rol_sistema_id' => ['prohibited'], 'permisos' => ['prohibited'], 'password' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('matricula'))) {
            $this->merge(['matricula' => filled(trim($this->input('matricula'))) ? mb_strtoupper(trim($this->input('matricula'))) : null]);
        }
        if (is_array($this->input('especialidades'))) {
            $this->merge(['especialidades' => array_map(fn ($valor) => is_string($valor) ? trim($valor) : $valor, $this->input('especialidades'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $usuario = UsuarioSistema::find($this->input('usuario_id'));
            if (filled($usuario?->ci) && Profesional::where('ci', mb_strtoupper(trim($usuario->ci)))
                ->when($this->route('profesional'), fn ($q, $p) => $q->whereKeyNot($p->id))->exists()) {
                $validator->errors()->add('usuario_id', 'Ya existe un profesional con el CI de este usuario. Edita su registro para vincularlo.');
            }
        }];
    }

    public function messages(): array
    {
        return ['usuario_id.required' => 'Selecciona un usuario registrado.', 'usuario_id.unique' => 'Este usuario ya tiene un perfil profesional.',
            'usuario_id.exists' => 'Selecciona un usuario del sistema, no una cuenta de paciente.', 'matricula.unique' => 'La matrícula ya pertenece a otro profesional.'];
    }
}
