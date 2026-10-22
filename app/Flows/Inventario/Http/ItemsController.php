<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Http\Controllers\Controller;
use App\Models\ItemInventario;
use App\Models\LoteItem;
use App\Models\ProductoBase;
use App\Models\UnidadInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    public function __construct(private AccesoAlmacenes $acceso) {}

    private const RELACIONES = ['productoBase.tipo', 'productoBase.categoria', 'productoBase.subcategoria', 'unidad', 'lotes'];

    public function opciones(): JsonResponse
    {
        return response()->json(['data' => [
            'productos' => $this->acceso->grupos(ProductoBase::with(['tipo', 'categoria', 'subcategoria']))->where('estado', 'ACTIVO')->whereHas('tipo', fn (Builder $q) => $q->where('activo', true)->whereIn('tipo_base', ['PRODUCTO', 'INSUMO']))->orderBy('nombre')->get(),
            'unidades' => UnidadInventario::where('activo', true)->orderBy('nombre')->get(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:150'], 'producto_base_id' => ['nullable', 'integer'], 'estado' => ['nullable', 'in:ACTIVO,INACTIVO'], 'page' => ['nullable', 'integer', 'min:1'], 'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'], 'orden' => ['nullable', 'in:id,nombre_comercial,marca,precio_1,precio_2,precio_3'], 'direccion' => ['nullable', 'in:asc,desc']]);
        $query = $this->acceso->items(ItemInventario::with(self::RELACIONES));
        $query->with(['lotes' => fn ($q) => $this->acceso->lotes($q->getQuery())]);
        if (! empty($datos['buscar'])) {
            $buscar = $datos['buscar'];
            $query->where(function (Builder $q) use ($buscar): void {
                $q->whereLike('nombre_comercial', '%'.$buscar.'%')->orWhereLike('marca', '%'.$buscar.'%')->orWhereLike('presentacion', '%'.$buscar.'%')->orWhereLike('codigo_barras', '%'.$buscar.'%')
                    ->orWhereHas('productoBase', fn (Builder $p) => $p->whereLike('nombre', '%'.$buscar.'%'))
                    ->orWhereHas('lotes', fn (Builder $l) => $this->acceso->lotes($l)->whereLike('numero', '%'.$buscar.'%'));
                if (preg_match('/^(ITM|PRD)-0*([1-9][0-9]{0,14})$/i', $buscar, $m)) {
                    $q->orWhere(strtoupper($m[1]) === 'ITM' ? 'id' : 'producto_base_id', $m[2]);
                }
            });
        }
        foreach (['producto_base_id', 'estado'] as $filtro) {
            if (! empty($datos[$filtro])) {
                $query->where($filtro, $datos[$filtro]);
            }
        }
        $orden = $datos['orden'] ?? 'nombre_comercial';
        $query->orderBy($orden, $datos['direccion'] ?? 'asc');
        if ($orden !== 'id') {
            $query->orderBy('id');
        }

        return response()->json($query->paginate($datos['por_pagina'] ?? 20));
    }

    public function store(GuardarItemRequest $request): JsonResponse
    {
        $this->acceso->exigirCatalogoCentral();
        $grupo = ProductoBase::with('tipo')->findOrFail($request->validated('producto_base_id'));
        abort_unless($grupo->tipo->activo && in_array($grupo->tipo->tipo_base, ['PRODUCTO', 'INSUMO']), 422, 'El tipo del agrupador debe estar activo y ser producto o insumo.');
        $item = ItemInventario::create($request->validated());

        return response()->json(['data' => $item->load(self::RELACIONES)], 201);
    }

    public function update(GuardarItemRequest $request, ItemInventario $item): JsonResponse
    {
        $this->acceso->exigirCatalogoCentral();
        $item->update($request->validated());

        return response()->json(['data' => $item->load(self::RELACIONES)]);
    }

    public function storeLote(GuardarLoteRequest $request, ItemInventario $item): JsonResponse
    {
        $this->acceso->exigirCatalogoCentral();
        abort_unless($item->estado === 'ACTIVO' && $item->productoBase->estado === 'ACTIVO', 422, 'Activa el ítem y su agrupador antes de agregar lotes.');
        $lote = $item->lotes()->create($request->validated());

        return response()->json(['data' => $lote], 201);
    }

    public function updateLote(GuardarLoteRequest $request, ItemInventario $item, LoteItem $lote): JsonResponse
    {
        $this->acceso->exigirCatalogoCentral();
        abort_unless($lote->item_id === $item->id, 404);
        $lote->update($request->validated());

        return response()->json(['data' => $lote]);
    }
}
