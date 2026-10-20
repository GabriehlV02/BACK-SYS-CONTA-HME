<?php

namespace App\Models;

use Database\Factories\ItemInventarioFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemInventario extends Model
{
    /** @use HasFactory<ItemInventarioFactory> */
    use HasFactory;

    protected $table = 'items_inventario';

    protected $fillable = ['producto_base_id', 'nombre_comercial', 'marca', 'presentacion', 'clave', 'descripcion', 'unidad_id', 'codigo_barras', 'precio_1', 'precio_2', 'precio_3', 'estado'];

    protected $hidden = ['clave'];

    protected $appends = ['codigo'];

    protected function casts(): array
    {
        return ['precio_1' => 'decimal:2', 'precio_2' => 'decimal:2', 'precio_3' => 'decimal:2'];
    }

    protected function codigo(): Attribute
    {
        return Attribute::make(get: fn (): string => 'ITM-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    public function productoBase(): BelongsTo
    {
        return $this->belongsTo(ProductoBase::class, 'producto_base_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadInventario::class, 'unidad_id');
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(LoteItem::class, 'item_id')->orderBy('numero');
    }
}
