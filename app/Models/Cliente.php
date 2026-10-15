<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = ['paciente_id', 'nit_ci', 'nombre_razon_social', 'telefono', 'correo', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function carritosVenta(): HasMany
    {
        return $this->hasMany(CarritoVenta::class);
    }
}
