<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Http\Controllers\Controller;
use App\Models\ExistenciaLote;
use App\Models\LoteItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class StockController extends Controller
{
    public function index(): JsonResponse
    {
        $acceso = app(AccesoAlmacenes::class);
        $ids = $acceso->ids();
        $lotes = $acceso->lotes(LoteItem::with(['item.productoBase.tipo', 'item.productoBase.categoria', 'item.productoBase.subcategoria', 'item.unidad', 'existencias.almacen.sucursal']))
            ->with(['existencias' => fn ($q) => $q->when($ids !== null, fn (Builder $q) => $q->whereIn('almacen_id', $ids))])->orderBy('item_id')->orderBy('numero')->get();
        $datos = $lotes->flatMap(function (LoteItem $lote): array {
            if ($lote->existencias->isEmpty()) {
                return [$this->datos($lote, null)];
            }

            return $lote->existencias->map(fn (ExistenciaLote $existencia): array => $this->datos($lote, $existencia))->all();
        })->values();

        return response()->json(['data' => $datos, 'meta' => ['registros' => $datos->count(), 'sin_ingresos' => $datos->whereNull('almacenId')->count()]]);
    }

    private function datos(LoteItem $lote, ?ExistenciaLote $existencia): array
    {
        $item = $lote->item;
        $grupo = $item->productoBase;

        return [
            'id' => $existencia ? 'existencia-'.$existencia->id : 'lote-'.$lote->id.'-sin-ingreso',
            'itemId' => $item->id, 'loteId' => $lote->id, 'codigo' => $item->codigo, 'nombre' => $item->nombre_comercial,
            'generico' => $grupo->nombre, 'codigoGenerico' => $grupo->codigo, 'tipo' => $grupo->tipo->nombre,
            'categoria' => $grupo->categoria?->nombre ?? 'Sin categoría', 'subcategoria' => $grupo->subcategoria?->nombre ?? 'Sin subcategoría',
            'unidad' => $item->unidad->nombre, 'presentacion' => $item->presentacion, 'marca' => $item->marca,
            'lote' => $lote->numero, 'vence' => $lote->vencimiento?->format('Y-m-d') ?? '',
            'almacenId' => $existencia?->almacen_id, 'almacen' => $existencia?->almacen->nombre ?? 'Sin almacén asignado',
            'sucursalId' => $existencia?->almacen->sucursal_id, 'sucursal' => $existencia?->almacen->sucursal->nombre ?? 'Sin sucursal asignada',
            'disponible' => $existencia ? (float) $existencia->cantidad : 0,
            'costoUnitario' => $existencia?->costo_unitario, 'fecha' => $existencia?->fecha_ingreso?->format('Y-m-d') ?? '',
            'precio1' => $lote->precio_1, 'precio2' => $lote->precio_2, 'precio3' => $lote->precio_3,
        ];
    }
}
