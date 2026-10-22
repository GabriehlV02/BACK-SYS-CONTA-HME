<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Flows\Inventario\RegistrarIngreso;
use App\Http\Controllers\Controller;
use App\Models\AlmacenInventario;
use App\Models\IngresoStock;
use App\Models\ItemInventario;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdquisicionesController extends Controller
{
    public function __construct(private AccesoAlmacenes $acceso) {}

    public function index(): JsonResponse
    {
        $ids = $this->acceso->ids();

        return response()->json(['data' => IngresoStock::with('detalles')->when($ids !== null, fn (Builder $q) => $q->whereIn('almacen_id', $ids))->orderByDesc('fecha')->orderByDesc('id')->get()->map(fn (IngresoStock $ingreso): array => $ingreso->datos())]);
    }

    public function opciones(): JsonResponse
    {
        return response()->json(['data' => [
            'almacenes' => $this->acceso->almacenes(AlmacenInventario::with('sucursal'))->where('activo', true)->whereHas('sucursal', fn (Builder $q) => $q->where('activo', true))->orderBy('nombre')->orderBy('id')->get(),
            'sucursales' => $this->acceso->sucursales(SucursalInventario::query())->where('activo', true)->orderBy('nombre')->get(),
            'items' => $this->acceso->items(ItemInventario::with(['productoBase.categoria', 'unidad', 'lotes']))->with(['lotes' => fn ($q) => $this->acceso->lotes($q->getQuery())])->where('estado', 'ACTIVO')
                ->whereHas('unidad', fn (Builder $q) => $q->where('activo', true))
                ->whereHas('productoBase', fn (Builder $q) => $q->where('estado', 'ACTIVO')->whereHas('tipo', fn (Builder $t) => $t->where('activo', true)->whereIn('tipo_base', ['PRODUCTO', 'INSUMO'])))
                ->orderBy('nombre_comercial')->orderBy('id')->get()->map(fn (ItemInventario $item): array => [
                    'itemId' => $item->id, 'codigo' => $item->codigo, 'nombre' => $item->nombre_comercial,
                    'grupoId' => $item->producto_base_id, 'codigoGenerico' => $item->productoBase->codigo,
                    'generico' => $item->productoBase->nombre, 'categoria' => $item->productoBase->categoria?->nombre ?? 'Sin categoría',
                    'unidadMedida' => $item->unidad->nombre, 'marca' => $item->marca, 'presentacion' => $item->presentacion,
                    'precio1' => $item->precio_1, 'precio2' => $item->precio_2, 'precio3' => $item->precio_3, 'lotes' => $item->lotes,
                ]),
        ]]);
    }

    public function store(GuardarIngresoRequest $request, RegistrarIngreso $registrar): JsonResponse
    {
        $this->acceso->exigir((int) $request->validated('almacen_id'));
        $ingreso = $registrar->handle($request->validated(), $request->user('contable')->getKey());

        return response()->json(['data' => $ingreso->datos()], $ingreso->wasRecentlyCreated ? 201 : 200);
    }

    public function storeAlmacen(Request $request): JsonResponse
    {
        $this->acceso->exigirCatalogoCentral();
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'], 'codigo' => ['required', 'string', 'max:30', Rule::unique('almacenes_inventario')],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales_inventario', 'id')->where('activo', true)],
            'sucursal_nombre' => ['required_without:sucursal_id', 'nullable', 'string', 'max:150'],
        ]);
        $almacen = DB::transaction(function () use ($datos): AlmacenInventario {
            $sucursalId = $datos['sucursal_id'] ?? SucursalInventario::create(['nombre' => $datos['sucursal_nombre'], 'codigo' => 'SUC-'.Str::random(20), 'activo' => true])->id;

            return AlmacenInventario::create(['nombre' => $datos['nombre'], 'codigo' => $datos['codigo'], 'sucursal_id' => $sucursalId, 'activo' => true]);
        });

        return response()->json(['data' => $almacen->load('sucursal')], 201);
    }

    public function estudios(): JsonResponse
    {
        $ids = $this->acceso->ids();
        $stock = DB::table('existencias_lote')->when($ids !== null, fn ($q) => $q->whereIn('almacen_id', $ids))->join('lotes_items', 'lotes_items.id', '=', 'existencias_lote.lote_id')
            ->selectRaw('lotes_items.item_id, SUM(existencias_lote.cantidad) as cantidad')->groupBy('lotes_items.item_id')->pluck('cantidad', 'item_id');

        return response()->json(['data' => $this->acceso->items(ItemInventario::query())->orderBy('id')->get()->map(fn (ItemInventario $item): array => [
            'codigo' => $item->codigo, 'nombre' => $item->nombre_comercial,
            'stock' => (float) ($stock[$item->id] ?? 0),
        ]), 'meta' => ['ventas_conectadas' => false]]);
    }
}
