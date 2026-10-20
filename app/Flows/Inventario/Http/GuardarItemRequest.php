<?php

namespace App\Flows\Inventario\Http;

use App\Models\ItemInventario;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $datos = [];
        foreach (['nombre_comercial', 'marca', 'presentacion'] as $campo) {
            if (is_string($this->input($campo))) {
                $datos[$campo] = Str::squish($this->input($campo));
            }
        }
        $datos['clave'] = hash('sha256', json_encode(array_map(fn (string $campo): string => Str::upper(Str::ascii($datos[$campo] ?? '')), ['nombre_comercial', 'marca', 'presentacion'])));
        if (is_string($this->input('codigo_barras'))) {
            $datos['codigo_barras'] = trim($this->input('codigo_barras')) ?: null;
        }
        $this->merge($datos);
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $grupo = $this->input('producto_base_id');
        $grupoValido = filter_var($grupo, FILTER_VALIDATE_INT) !== false ? (int) $grupo : 0;

        return [
            'producto_base_id' => ['bail', 'required', 'integer', Rule::exists('productos_base', 'id')->where(function (Builder $query) use ($item): void {
                $query->where(function (Builder $query) use ($item): void {
                    $query->where('estado', 'ACTIVO');
                    if ($item instanceof ItemInventario) {
                        $query->orWhere('id', $item->producto_base_id);
                    }
                });
            }), Rule::when($item instanceof ItemInventario, [Rule::in([$item?->producto_base_id])])],
            'nombre_comercial' => ['required', 'string', 'max:150'],
            'marca' => ['required', 'string', 'max:100'],
            'presentacion' => ['required', 'string', 'max:150'],
            'clave' => ['required', Rule::unique('items_inventario', 'clave')->where('producto_base_id', $grupoValido)->ignore($item)],
            'unidad_id' => ['bail', 'required', 'uuid', Rule::exists('unidades_inventario', 'id')->where(function (Builder $query) use ($item): void {
                $query->where('activo', true);
                if ($item instanceof ItemInventario) {
                    $query->orWhere('id', $item->unidad_id);
                }
            })],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'codigo_barras' => ['bail', 'nullable', 'string', 'max:100', Rule::unique('items_inventario', 'codigo_barras')->ignore($item)],
            'precio_1' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'precio_2' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'precio_3' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'stock' => ['prohibited'], 'cantidad' => ['prohibited'], 'lotes' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_base_id.exists' => 'Selecciona un producto agrupador activo.',
            'producto_base_id.in' => 'El agrupador de un ítem existente no se puede cambiar.',
            'clave.unique' => 'Ya existe ese nombre comercial, marca y presentación en el agrupador.',
            'unidad_id.exists' => 'Selecciona una unidad activa.',
            'codigo_barras.unique' => 'Ese código de barras ya pertenece a otro ítem.',
            '*.decimal' => 'Cada precio admite hasta dos decimales.',
            '*.min' => 'El precio no puede ser negativo.',
        ];
    }
}
