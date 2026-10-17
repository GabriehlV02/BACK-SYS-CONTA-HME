<?php

namespace App\Models;

use Database\Factories\SubcategoriaInventarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcategoriaInventario extends Model
{
    /** @use HasFactory<SubcategoriaInventarioFactory> */
    use HasFactory, HasUuids;

    protected $table = 'subcategorias_inventario';

    protected $fillable = ['nombre', 'clave', 'categoria_id', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaInventario::class, 'categoria_id');
    }
}
