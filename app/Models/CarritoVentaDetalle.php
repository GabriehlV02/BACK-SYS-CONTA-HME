<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarritoVentaDetalle extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'carrito_venta_id', 'item_id', 'codigo_item', 'nombre_item', 'tipo_item',
        'cantidad', 'precio_unitario', 'descuento', 'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function carritoVenta(): BelongsTo
    {
        return $this->belongsTo(CarritoVenta::class);
    }
}
