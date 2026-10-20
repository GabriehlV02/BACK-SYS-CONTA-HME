<?php

namespace App\Models;

use Database\Factories\LoteItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoteItem extends Model
{
    /** @use HasFactory<LoteItemFactory> */
    use HasFactory;

    protected $table = 'lotes_items';

    protected $fillable = ['numero', 'clave', 'vencimiento', 'precio_1', 'precio_2', 'precio_3'];

    protected $hidden = ['clave'];

    protected function casts(): array
    {
        return ['vencimiento' => 'date:Y-m-d', 'precio_1' => 'decimal:2', 'precio_2' => 'decimal:2', 'precio_3' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemInventario::class, 'item_id');
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(ExistenciaLote::class, 'lote_id');
    }
}
