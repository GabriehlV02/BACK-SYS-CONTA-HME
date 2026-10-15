<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarritoVenta extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'carritos_venta';

    protected $fillable = [
        'codigo', 'cliente_id', 'usuario_sistema_id', 'estado', 'moneda',
        'subtotal', 'descuento', 'total', 'observaciones', 'cobrada_en', 'anulada_en',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'cobrada_en' => 'datetime',
            'anulada_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CarritoVentaDetalle::class);
    }
}
