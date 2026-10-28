<?php

namespace App\Flows\Usuarios\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Caja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            'caja_ids' => ['sometimes', 'array', 'max:100'],
            'caja_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cajas', 'id')->where('activo', true)],
            'almacenes' => ['sometimes', 'array', 'max:100'],
            'almacenes.*' => ['required', 'string', 'max:100', 'distinct'],
            'sucursal_ids' => ['sometimes', 'array', 'max:100'],
            'sucursal_ids.*' => ['required', 'integer', 'distinct', Rule::exists('sucursales_inventario', 'id')->where('activo', true)],
            'almacen_ids' => ['sometimes', 'array', 'max:100'],
            'almacen_ids.*' => ['required', 'integer', 'distinct', Rule::exists('almacenes_inventario', 'id')->where('activo', true)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->isEmpty()) {
                return;
            }
            $almacenes = $this->input('almacen_ids', []);
            if ($this->has('caja_ids') || $this->has('sucursal_ids') || $this->has('almacen_ids')) {
                $registro = $this->route('usuario') ? UsuarioSistema::find($this->route('usuario')) : null;
                $cajas = $this->input('caja_ids', $registro?->cajasAsignadas()->pluck('cajas.id')->all() ?? []);
                $validas = Caja::where('activo', true)->whereIn('sucursal_id', $this->input('sucursal_ids', []))
                    ->whereHas('almacenes', fn ($q) => $q->whereIn('almacenes_inventario.id', $almacenes)->where('activo', true))->pluck('id')->all();
                if (array_diff($cajas, $validas)) {
                    $validator->errors()->add('caja_ids', 'Selecciona cajas activas de las sucursales y almacenes asignados.');
                }
            }
            $permitidos = app(AccesoAlmacenes::class)->ids();
            if ($permitidos !== null) {
                if (array_diff(array_map('intval', $almacenes), $permitidos) !== []) {
                    $validator->errors()->add('almacen_ids', 'Solo puedes asignar tus almacenes autorizados.');
                }
                $sucursales = DB::table('almacenes_inventario')->whereIn('id', $permitidos)->pluck('sucursal_id')->all();
                if (array_diff(array_map('intval', $this->input('sucursal_ids', [])), $sucursales) !== []) {
                    $validator->errors()->add('sucursal_ids', 'Solo puedes asignar sucursales de tus almacenes autorizados.');
                }
            }
            if ($almacenes !== [] && DB::table('almacenes_inventario')->whereIn('id', $almacenes)
                ->whereNotIn('sucursal_id', $this->input('sucursal_ids', []))->exists()) {
                $validator->errors()->add('almacen_ids', 'Selecciona almacenes de las sucursales asignadas al usuario.');
            }
        }, function (Validator $validator): void {
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
