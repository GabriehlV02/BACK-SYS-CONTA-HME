<?php

namespace App\Flows\Inventario\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GuardarLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $numero = is_string($this->input('numero')) ? Str::squish($this->input('numero')) : '';
        $this->merge(['numero' => $numero, 'clave' => Str::upper(Str::ascii($numero))]);
    }

    public function rules(): array
    {
        return [
            'numero' => ['required', 'string', 'max:100'],
            'clave' => ['required', 'string', 'max:100', Rule::unique('lotes_items', 'clave')->where('item_id', $this->route('item')->id)->ignore($this->route('lote'))],
            'vencimiento' => ['nullable', 'date_format:Y-m-d'],
            'precio_1' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'precio_2' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'precio_3' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'cantidad' => ['prohibited'], 'stock' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['clave.unique' => 'Este lote ya está registrado para el ítem.', '*.decimal' => 'Cada precio admite hasta dos decimales.', '*.min' => 'El precio no puede ser negativo.'];
    }
}
