<?php

namespace App\Flows\Inventario\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarProductoBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $nombre = Str::squish($this->input('nombre'));
            $this->merge(['nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre))]);
        } else {
            $this->merge(['clave' => null]);
        }
    }

    public function rules(): array
    {
        $producto = $this->route('producto');
        $categoria = $this->input('categoria_id');

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'clave' => ['required', 'string', 'max:150', Rule::unique('productos_base', 'clave')->ignore($producto)],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'tipo_id' => ['required', 'uuid', Rule::exists('tipos_inventario', 'id')->where('activo', true)->whereIn('tipo_base', ['PRODUCTO', 'INSUMO'])],
            'categoria_id' => ['nullable', 'uuid', Rule::exists('categorias_inventario', 'id')->where('activo', true)],
            'subcategoria_id' => ['nullable', 'uuid', Rule::prohibitedIf(empty($categoria)), Rule::exists('subcategorias_inventario', 'id')->where('activo', true)->where('categoria_id', is_string($categoria) && Str::isUuid($categoria) ? $categoria : null)],
            'clasificacion_id' => ['nullable', 'uuid', Rule::exists('clasificaciones_inventario', 'id')->where('activo', true)],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'precio' => ['prohibited'], 'precio_compra' => ['prohibited'], 'precio_venta' => ['prohibited'],
            'stock' => ['prohibited'], 'lote' => ['prohibited'], 'lotes' => ['prohibited'],
            'presentacion' => ['prohibited'], 'unidad_id' => ['prohibited'], 'vencimiento' => ['prohibited'],
            'serie' => ['prohibited'], 'codigo_barras' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del producto base.',
            'clave.unique' => 'Ya existe un producto base con ese nombre.',
            'tipo_id.exists' => 'Selecciona un tipo activo de producto o insumo.',
            'categoria_id.exists' => 'Selecciona una categoría activa.',
            'subcategoria_id.exists' => 'Selecciona una subcategoría activa de la categoría elegida.',
            'subcategoria_id.prohibited' => 'Selecciona primero una categoría.',
            'clasificacion_id.exists' => 'Selecciona una clasificación activa.',
            '*.prohibited' => 'Los datos de presentación, precio y lote se registrarán en los ítems.',
        ];
    }
}
