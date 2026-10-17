<?php

namespace App\Models;

use Database\Factories\CategoriaInventarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaInventario extends Model
{
    /** @use HasFactory<CategoriaInventarioFactory> */
    use HasFactory, HasUuids;

    protected $table = 'categorias_inventario';

    protected $fillable = ['nombre', 'clave', 'prefijo', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(SubcategoriaInventario::class, 'categoria_id');
    }
}
