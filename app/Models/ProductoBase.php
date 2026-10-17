<?php

namespace App\Models;

use Database\Factories\ProductoBaseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoBase extends Model
{
    /** @use HasFactory<ProductoBaseFactory> */
    use HasFactory;

    protected $table = 'productos_base';

    protected $fillable = ['nombre', 'clave', 'descripcion', 'tipo_id', 'categoria_id', 'subcategoria_id', 'clasificacion_id', 'estado'];

    protected $hidden = ['clave'];

    protected $appends = ['codigo'];

    protected function codigo(): Attribute
    {
        return Attribute::make(get: fn (): string => 'PRD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoInventario::class, 'tipo_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaInventario::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(SubcategoriaInventario::class, 'subcategoria_id');
    }

    public function clasificacion(): BelongsTo
    {
        return $this->belongsTo(ClasificacionInventario::class, 'clasificacion_id');
    }
}
