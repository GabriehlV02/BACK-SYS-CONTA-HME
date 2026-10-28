<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Http\Controllers\Controller;
use App\Models\AlmacenInventario;
use App\Models\Caja;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CajasController extends Controller
{
    public function index(AccesoAlmacenes $acceso): JsonResponse
    {
        $ids = $acceso->ids();
        $cajas = Caja::query()->with(['almacenes' => fn ($q) => $q->when($ids !== null, fn ($q) => $q->whereIn('almacenes_inventario.id', $ids))])
            ->when($ids !== null, fn (Builder $q) => $q->whereHas('almacenes', fn (Builder $a) => $a->whereIn('almacenes_inventario.id', $ids)))
            ->orderBy('codigo')->get();

        return response()->json(['data' => $cajas->map(fn (Caja $caja): array => $this->datos($caja))]);
    }

    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('codigo'))) {
            $request->merge(['codigo' => Str::upper(trim($request->input('codigo')))]);
        }
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['required', 'string', 'max:30', Rule::unique('cajas', 'codigo')],
            'sucursalId' => ['required', 'integer', Rule::exists('sucursales_inventario', 'id')->where('activo', true)],
            'almacenIds' => ['required', 'array', 'min:1', 'max:100'],
            'almacenIds.*' => ['required', 'integer', 'distinct', Rule::exists('almacenes_inventario', 'id')->where('activo', true)],
            'tiposItem' => ['required', 'array', 'min:1', 'max:3'],
            'tiposItem.*' => ['required', 'string', 'distinct', Rule::in(['Productos', 'Insumos', 'Servicios'])],
            'creado_por' => ['prohibited'],
        ]);
        $caja = DB::transaction(function () use ($datos, $request): Caja {
            $sucursal = SucursalInventario::whereKey($datos['sucursalId'])->lockForUpdate()->firstOrFail();
            if (! $sucursal->activo) {
                throw ValidationException::withMessages(['sucursalId' => 'Selecciona una sucursal activa.']);
            }
            $almacenes = AlmacenInventario::whereIn('id', $datos['almacenIds'])->orderBy('id')->lockForUpdate()->get();
            if ($almacenes->count() !== count($datos['almacenIds']) || $almacenes->contains(fn (AlmacenInventario $a): bool => ! $a->activo || $a->sucursal_id !== $sucursal->id)) {
                throw ValidationException::withMessages(['almacenIds' => 'Los almacenes deben estar activos y pertenecer a la sucursal seleccionada.']);
            }
            if (array_diff($datos['tiposItem'], $almacenes->pluck('tipos_item')->flatten()->unique()->all())) {
                throw ValidationException::withMessages(['tiposItem' => 'Los tipos de items deben estar permitidos por los almacenes seleccionados.']);
            }
            $caja = Caja::create(['nombre' => trim($datos['nombre']), 'codigo' => $datos['codigo'], 'sucursal_id' => $sucursal->id,
                'creado_por' => $request->user('contable')->getKey(), 'tipos_item' => $datos['tiposItem'], 'activo' => true]);
            $caja->almacenes()->sync($datos['almacenIds']);

            return $caja->load('almacenes');
        });

        return response()->json(['data' => $this->datos($caja)], 201);
    }

    private function datos(Caja $caja): array
    {
        return ['id' => $caja->id, 'nombre' => $caja->nombre, 'codigo' => $caja->codigo, 'sucursalId' => $caja->sucursal_id,
            'almacenIds' => $caja->almacenes->modelKeys(), 'tiposItem' => $caja->tipos_item, 'estado' => $caja->activo ? 'Activa' : 'Inactiva'];
    }
}
