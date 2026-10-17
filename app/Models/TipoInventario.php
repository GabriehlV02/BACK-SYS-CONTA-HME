<?php

namespace App\Models;

use Database\Factories\TipoInventarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoInventario extends Model
{
    /** @use HasFactory<TipoInventarioFactory> */
    use HasFactory, HasUuids;

    protected $table = 'tipos_inventario';

    protected $fillable = ['nombre', 'clave', 'descripcion', 'tipo_base', 'activo'];

    protected $attributes = ['activo' => true];

    public function productosBase(): HasMany
    {
        return $this->hasMany(ProductoBase::class, 'tipo_id');
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
