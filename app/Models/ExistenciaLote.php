<?php

namespace App\Models;

use Database\Factories\ExistenciaLoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExistenciaLote extends Model
{
    /** @use HasFactory<ExistenciaLoteFactory> */
    use HasFactory;

    protected $table = 'existencias_lote';

    protected $fillable = ['lote_id', 'almacen_id', 'cantidad', 'costo_unitario', 'fecha_ingreso'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'costo_unitario' => 'decimal:4', 'fecha_ingreso' => 'date:Y-m-d'];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteItem::class, 'lote_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(AlmacenInventario::class, 'almacen_id');
    }
}
