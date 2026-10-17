<?php

namespace App\Models;

use Database\Factories\UnidadInventarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadInventario extends Model
{
    /** @use HasFactory<UnidadInventarioFactory> */
    use HasFactory, HasUuids;

    protected $table = 'unidades_inventario';

    protected $fillable = ['nombre', 'clave', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
