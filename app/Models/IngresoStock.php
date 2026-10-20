<?php

namespace App\Models;

use Database\Factories\IngresoStockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngresoStock extends Model
{
    /** @use HasFactory<IngresoStockFactory> */
    use HasFactory;

    protected $table = 'ingresos_stock';

    protected $fillable = ['solicitud_id', 'huella', 'fecha', 'proveedor', 'comprobante', 'almacen_id', 'almacen_nombre', 'usuario_id', 'total'];

    protected $hidden = ['huella'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d', 'total' => 'decimal:2'];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleIngresoStock::class, 'ingreso_id')->orderBy('id');
    }

    public function datos(): array
    {
        return [
            'id' => 'ING-'.$this->fecha->format('Y').'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
            'fecha' => $this->fecha->format('Y-m-d'), 'proveedor' => $this->proveedor,
            'comprobante' => $this->comprobante ?? '', 'almacenId' => $this->almacen_id,
            'almacen' => $this->almacen_nombre, 'total' => $this->total,
            'lineas' => $this->detalles->map(fn (DetalleIngresoStock $detalle): array => [
                ...$detalle->datos, 'id' => (string) $detalle->id, 'loteId' => $detalle->lote_id,
                'cantidad' => (float) $detalle->cantidad, 'costoUnitario' => (float) $detalle->costo_unitario,
                'subtotal' => $detalle->subtotal,
            ])->all(),
        ];
    }
}
