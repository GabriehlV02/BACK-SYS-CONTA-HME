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

class GuardarServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = is_string($this->input('nombre')) ? Str::squish($this->input('nombre')) : '';
        $clave = fn (string $campo): string => is_string($this->input($campo)) ? Str::upper(Str::ascii(trim($this->input($campo)))) : '';
        $categoria = CategoriaInventario::where('clave', $clave('categoria'))->first();
        $tipo = is_string($this->input('tipoOpcion')) ? 'tipoOpcion' : 'tipo';
        $this->merge([
            'nombre' => $nombre, 'clave' => Str::upper(Str::ascii($nombre)),
            'tipo_id' => TipoInventario::where('clave', $clave($tipo))->value('id'),
            'categoria_id' => $categoria?->id,
            'subcategoria_id' => $categoria ? SubcategoriaInventario::where('categoria_id', $categoria->id)->where('clave', $clave('grupo'))->value('id') : null,
            'unidad_id' => UnidadInventario::where('clave', $clave('unidadMedida'))->value('id'),
            'clasificacion_id' => $clave('clasificacion') !== '' ? ClasificacionInventario::where('clave', $clave('clasificacion'))->value('id') : null,
            'precio_venta' => $this->input('precioVenta'),
            'numero_serie' => $this->boolean('usarNumeroSerie') ? $this->input('numeroSerie') : null,
            'estado' => $clave('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'clave' => ['required', 'string', 'max:150', Rule::unique('servicios', 'clave')->ignore($this->route('servicio'))],
            'descripcion' => ['required', 'string', 'max:1000'],
            'tipo_id' => ['required', 'uuid', Rule::exists('tipos_inventario', 'id')->where('activo', true)->where('tipo_base', 'SERVICIO')],
            'categoria_id' => ['required', 'uuid', Rule::exists('categorias_inventario', 'id')->where('activo', true)],
            'subcategoria_id' => ['required', 'uuid', Rule::exists('subcategorias_inventario', 'id')->where('activo', true)->where('categoria_id', $this->input('categoria_id'))],
            'unidad_id' => ['required', 'uuid', Rule::exists('unidades_inventario', 'id')->where('activo', true)],
            'clasificacion' => ['nullable', 'string', Rule::exists('clasificaciones_inventario', 'clave')->where('activo', true)],
            'clasificacion_id' => ['nullable', 'uuid', Rule::exists('clasificaciones_inventario', 'id')->where('activo', true)],
            'precio_venta' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'usarNumeroSerie' => ['sometimes', 'boolean'],
            'numero_serie' => ['nullable', 'string', 'max:100', Rule::requiredIf($this->boolean('usarNumeroSerie'))],
            'imagen' => ['nullable', 'string', 'max:2000000', 'regex:~^data:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=]+$~'],
            'stock' => ['prohibited'], 'lote' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del servicio.',
            'clave.unique' => 'Ya existe un servicio con ese nombre.',
            'tipo_id.required' => 'Selecciona un tipo de servicio activo.',
            'tipo_id.exists' => 'Selecciona un tipo de servicio activo.',
            'categoria_id.required' => 'Selecciona una categoría activa.',
            'categoria_id.exists' => 'Selecciona una categoría activa.',
            'subcategoria_id.required' => 'Selecciona una subcategoría activa de la categoría elegida.',
            'subcategoria_id.exists' => 'Selecciona una subcategoría activa de la categoría elegida.',
            'unidad_id.required' => 'Selecciona una unidad de medida activa.',
            'unidad_id.exists' => 'Selecciona una unidad de medida activa.',
            'precio_venta.min' => 'El precio no puede ser negativo.',
            'precio_venta.decimal' => 'El precio admite hasta dos decimales.',
        ];
    }

    public function datosServicio(): array
    {
        return $this->safe()->except(['clasificacion', 'usarNumeroSerie']);
    }
}
