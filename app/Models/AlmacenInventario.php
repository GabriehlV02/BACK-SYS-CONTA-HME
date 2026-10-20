<?php

namespace App\Models;

use Database\Factories\AlmacenInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlmacenInventario extends Model
{
    /** @use HasFactory<AlmacenInventarioFactory> */
    use HasFactory;

    protected $table = 'almacenes_inventario';

    protected $fillable = ['nombre', 'codigo', 'sucursal_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(SucursalInventario::class, 'sucursal_id');
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(ExistenciaLote::class, 'almacen_id');
    }
}
