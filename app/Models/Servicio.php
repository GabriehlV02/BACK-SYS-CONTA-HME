<?php

namespace App\Models;

use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = ['nombre', 'clave', 'descripcion', 'tipo_id', 'categoria_id', 'subcategoria_id', 'unidad_id', 'clasificacion_id', 'precio_venta', 'estado', 'numero_serie', 'imagen'];

    protected function casts(): array
    {
        return ['precio_venta' => 'decimal:2'];
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

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadInventario::class, 'unidad_id');
    }

    public function clasificacion(): BelongsTo
    {
        return $this->belongsTo(ClasificacionInventario::class, 'clasificacion_id');
    }
}
