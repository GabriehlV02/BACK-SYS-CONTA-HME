<?php

namespace App\Models;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Factories\CajaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Caja extends Model
{
    /** @use HasFactory<CajaFactory> */
    use HasFactory;

    protected $fillable = ['codigo', 'nombre', 'sucursal_id', 'creado_por', 'tipos_item', 'activo'];

    protected function casts(): array
    {
        return ['tipos_item' => 'array', 'activo' => 'boolean'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(SucursalInventario::class, 'sucursal_id');
    }

    public function almacenes(): BelongsToMany
    {
        return $this->belongsToMany(AlmacenInventario::class, 'almacen_caja', 'caja_id', 'almacen_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(UsuarioSistema::class, 'creado_por');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(UsuarioSistema::class, 'caja_usuario', 'caja_id', 'usuario_id');
    }
}
