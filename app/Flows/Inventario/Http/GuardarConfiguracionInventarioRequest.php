<?php

namespace App\Flows\Inventario\Http;

use App\Models\CategoriaInventario;
use App\Models\ClasificacionInventario;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use App\Models\UnidadInventario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarConfiguracionInventarioRequest extends FormRequest
{
    public const MODELOS = [
        'tipos' => TipoInventario::class,
        'unidades' => UnidadInventario::class,
        'categorias' => CategoriaInventario::class,
        'subcategorias' => SubcategoriaInventario::class,
        'clasificaciones' => ClasificacionInventario::class,
    ];

    public function authorize(): bool
    {
        return $this->user('contable')?->can('INVENTARIO_GESTIONAR') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');
        if (is_string($nombre)) {
            $this->merge(['nombre' => trim($nombre), 'clave' => Str::upper(Str::ascii(trim($nombre)))]);
        }
        if (is_string($this->input('prefijo'))) {
            $this->merge(['prefijo' => Str::upper(trim($this->input('prefijo')))]);
        }
    }

    public function rules(): array
    {
        $catalogo = $this->route('catalogo');
        $modelo = self::MODELOS[$catalogo];
        $unico = Rule::unique((new $modelo)->getTable(), 'clave');
        if ($catalogo === 'subcategorias') {
            $categoria = $this->input('categoria_id');
            $unico->where('categoria_id', is_string($categoria) && Str::isUuid($categoria) ? $categoria : null);
        }
        $rules = [
            'nombre' => ['bail', 'required', 'string', 'max:100'],
            'clave' => ['bail', 'required', 'string', 'max:100', $unico],
        ];
        if ($catalogo === 'tipos') {
            $rules['descripcion'] = ['nullable', 'string', 'max:1000'];
            $rules['tipo_base'] = ['required', Rule::in(['PRODUCTO', 'INSUMO', 'SERVICIO'])];
        }
        if ($catalogo === 'categorias') {
            $rules['prefijo'] = ['bail', 'required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', Rule::unique('categorias_inventario', 'prefijo')];
        }
        if ($catalogo === 'subcategorias') {
            $rules['categoria_id'] = ['bail', 'required', 'uuid', Rule::exists('categorias_inventario', 'id')->where('activo', true)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre.',
            'nombre.max' => 'El nombre no debe superar los 100 caracteres.',
            'clave.unique' => 'Ya existe un registro con ese nombre.',
            'prefijo.required' => 'Ingresa el prefijo para los códigos de esta categoría.',
            'prefijo.unique' => 'Ese prefijo ya está en uso.',
            'prefijo.regex' => 'El prefijo solo admite letras sin tildes y números.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'categoria_id.exists' => 'Selecciona una categoría activa.',
            'tipo_base.required' => 'Selecciona el tipo base.',
        ];
    }
}
