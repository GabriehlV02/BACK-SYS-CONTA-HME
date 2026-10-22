<?php

namespace App\Flows\Inventario;

use App\Models\ItemInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AccesoAlmacenes
{
    public function ids(): ?array
    {
        $usuario = Auth::guard('contable')->user();
        abort_unless($usuario, 401);
        if ($usuario->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->exists()) {
            return null;
        }

        return $usuario->almacenesAsignados()->where('almacenes_inventario.activo', true)
            ->whereHas('sucursal', fn (Builder $q) => $q->where('activo', true))
            ->whereIn('sucursal_id', $usuario->sucursalesAsignadas()->select('sucursales_inventario.id'))
            ->pluck('almacenes_inventario.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function almacenes(Builder $query): Builder
    {
        $ids = $this->ids();

        return $ids === null ? $query : $query->whereIn('almacenes_inventario.id', $ids);
    }

    public function sucursales(Builder $query): Builder
    {
        $ids = $this->ids();

        return $ids === null ? $query : $query->whereHas('almacenes', fn (Builder $q) => $q->whereIn('id', $ids));
    }

    public function lotes(Builder $query): Builder
    {
        $ids = $this->ids();

        return $ids === null ? $query : $query->whereHas('existencias', fn (Builder $q) => $q->whereIn('almacen_id', $ids));
    }

    public function items(Builder $query): Builder
    {
        $ids = $this->ids();

        return $ids === null ? $query : $query->whereHas('lotes.existencias', fn (Builder $q) => $q->whereIn('almacen_id', $ids));
    }

    public function grupos(Builder $query): Builder
    {
        return $this->ids() === null ? $query : $query->whereIn('productos_base.id', $this->items(ItemInventario::query())->select('producto_base_id'));
    }

    public function exigir(int $almacenId): void
    {
        $ids = $this->ids();
        abort_unless($ids === null || in_array($almacenId, $ids, true), 403, 'No tienes acceso a ese almacén.');
    }

    public function exigirCatalogoCentral(): void
    {
        abort_unless($this->ids() === null, 403, 'El catálogo compartido se administra desde la cuenta administradora.');
    }
}
