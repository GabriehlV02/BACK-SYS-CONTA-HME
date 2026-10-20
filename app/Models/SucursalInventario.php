<?php

namespace App\Models;

use Database\Factories\SucursalInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SucursalInventario extends Model
{
    /** @use HasFactory<SucursalInventarioFactory> */
    use HasFactory;

    protected $table = 'sucursales_inventario';

    protected $fillable = ['nombre', 'codigo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(AlmacenInventario::class, 'sucursal_id');
    }
}
