<?php

namespace App\Flows\Ventas\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Models\AlmacenInventario;
use App\Models\ExistenciaLote;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CatalogoVentasController
{
    public function __invoke(AccesoAlmacenes $acceso): JsonResponse
    {
        $almacenes = $acceso->almacenes(AlmacenInventario::query())->with('sucursal')->where('activo', true)
            ->whereHas('sucursal', fn (Builder $q) => $q->where('activo', true))->orderBy('nombre')->get();
        $existencias = ExistenciaLote::with(['lote.item.productoBase.tipo', 'lote.item.productoBase.categoria'])
            ->whereIn('almacen_id', $almacenes->modelKeys())->whereHas('lote.item', fn (Builder $q) => $q->where('estado', 'ACTIVO')
            ->whereHas('unidad', fn (Builder $q) => $q->where('activo', true))
            ->whereHas('productoBase', fn (Builder $q) => $q->where('estado', 'ACTIVO')->whereHas('tipo', fn (Builder $q) => $q->where('activo', true))))
            ->orderBy('id')->get();
        $productos = $existencias->map(fn (ExistenciaLote $e): array => [
            'id' => 'existencia-'.$e->id, 'codigo' => $e->lote->item->codigo, 'nombre' => $e->lote->item->nombre_comercial,
            'tipo' => $e->lote->item->productoBase->tipo->tipo_base === 'INSUMO' ? 'Insumo' : 'Producto',
            'categoria' => $e->lote->item->productoBase->categoria?->nombre ?? '',
            'precio' => (float) $e->lote->precio_1, 'stock' => (float) $e->cantidad, 'almacenId' => $e->almacen_id,
        ]);
        $servicios = Servicio::with('categoria')->where('estado', 'ACTIVO')
            ->whereHas('tipo', fn (Builder $q) => $q->where('activo', true))->whereHas('unidad', fn (Builder $q) => $q->where('activo', true))
            ->orderBy('nombre')->get()->map(fn (Servicio $s): array => [
                'id' => 'servicio-'.$s->id, 'codigo' => 'SRV-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT), 'nombre' => $s->nombre, 'tipo' => 'Servicio',
                'categoria' => $s->categoria?->nombre ?? '', 'precio' => (float) $s->precio_venta,
            ]);

        return response()->json(['data' => $productos->concat($servicios)->values(), 'almacenes' => $almacenes->map(fn (AlmacenInventario $a): array => [
            'id' => $a->id, 'nombre' => $a->sucursal->nombre.' - '.$a->nombre,
        ])]);
    }
}
