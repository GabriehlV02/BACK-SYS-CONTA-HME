<?php

namespace App\Flows\Inventario\Http;

use Illuminate\Foundation\Http\FormRequest;

class GuardarIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solicitud_id' => ['required', 'uuid'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'proveedor' => ['required', 'string', 'max:200'],
            'comprobante' => ['nullable', 'string', 'max:100'],
            'almacen_id' => ['required', 'integer', 'exists:almacenes_inventario,id'],
            'lineas' => ['required', 'array', 'min:1', 'max:100'],
            'lineas.*.item_id' => ['required', 'integer', 'exists:items_inventario,id'],
            'lineas.*.lote' => ['required', 'string', 'max:100'],
            'lineas.*.vence' => ['nullable', 'date_format:Y-m-d'],
            'lineas.*.cantidad' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:100000'],
            'lineas.*.costoUnitario' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100000'],
            'lineas.*.precio1' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'lineas.*.precio2' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'lineas.*.precio3' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'lineas.*.observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['fecha.before_or_equal' => 'La recepción no puede tener una fecha futura.',
            'proveedor.required' => 'Indica el proveedor del ingreso.',
            'almacen_id.required' => 'Selecciona un almacén destino.',
            'lineas.required' => 'Agrega al menos un producto al ingreso.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser mayor que cero.',
            'lineas.*.cantidad.decimal' => 'La cantidad admite hasta tres decimales.',
            'lineas.*.costoUnitario.min' => 'El costo no puede ser negativo.',
            'lineas.*.precio1.min' => 'El precio no puede ser negativo.',
            'lineas.*.precio2.min' => 'El precio no puede ser negativo.',
            'lineas.*.precio3.min' => 'El precio no puede ser negativo.'];
    }
}
