<?php

namespace App\Flows\Usuarios\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contable')?->can('ROLES_GESTIONAR') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('roles', 'nombre')->ignore($this->route('rol'))],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['sometimes', 'boolean'],
            'sistemas' => ['required', 'array', 'min:1'],
            'sistemas.*' => ['required', 'string', 'distinct', Rule::exists('sistemas', 'codigo')->where('activo', true)],
            'permisos' => ['present', 'array'],
            'permisos.*' => ['required', 'string', 'distinct', Rule::exists('permisos', 'codigo')->where('activo', true)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $sistemas = DB::table('sistemas')->whereIn('codigo', $this->input('sistemas'))->pluck('id');
            if (DB::table('permisos')->whereIn('codigo', $this->input('permisos'))->whereNotIn('sistema_id', $sistemas)->exists()) {
                $validator->errors()->add('permisos', 'Los permisos deben pertenecer a los sistemas seleccionados.');
            }
            $usuario = $this->user('contable');
            if (! $usuario->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->exists()
                && array_diff($this->input('permisos'), $usuario->permisosContables()) !== []) {
                $validator->errors()->add('permisos', 'No puedes otorgar permisos que no tienes asignados.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre del rol.',
            'nombre.unique' => 'Ya existe un rol con ese nombre.',
            'sistemas.min' => 'Selecciona al menos un sistema.',
            'sistemas.*.exists' => 'Uno de los sistemas no está habilitado.',
            'permisos.*.exists' => 'Uno de los permisos no está habilitado.',
        ];
    }
}
