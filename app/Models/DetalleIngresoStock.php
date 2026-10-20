<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleIngresoStock extends Model
{
    protected $table = 'detalles_ingreso_stock';

    protected $fillable = ['lote_id', 'cantidad', 'costo_unitario', 'subtotal', 'datos'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'costo_unitario' => 'decimal:4', 'subtotal' => 'decimal:2', 'datos' => 'array'];
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(IngresoStock::class, 'ingreso_id');
    }
}
