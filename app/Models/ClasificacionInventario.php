<?php

namespace App\Models;

use Database\Factories\ClasificacionInventarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasificacionInventario extends Model
{
    /** @use HasFactory<ClasificacionInventarioFactory> */
    use HasFactory, HasUuids;

    protected $table = 'clasificaciones_inventario';

    protected $fillable = ['nombre', 'clave', 'activo'];

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
