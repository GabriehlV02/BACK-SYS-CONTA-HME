<?php

namespace App\Models;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Factories\DispositivoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispositivo extends Model
{
    /** @use HasFactory<DispositivoFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'codigo', 'tipo', 'sucursal_id', 'usuario_id', 'area', 'uso', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(SucursalInventario::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(UsuarioSistema::class, 'usuario_id');
    }
}
