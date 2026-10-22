<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Http\Controllers\Controller;
use App\Models\AlmacenInventario;
use App\Models\Dispositivo;
use App\Models\SucursalInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConfiguracionGeneralController extends Controller
{
    public function index(): JsonResponse
    {
        $acceso = app(AccesoAlmacenes::class);
        $ids = $acceso->ids();
        $sucursalIds = $acceso->almacenes(AlmacenInventario::query())->select('sucursal_id');

        return response()->json(['data' => [
            'sucursales' => $acceso->sucursales(SucursalInventario::query())->withCount(['almacenes' => fn (Builder $q) => $acceso->almacenes($q)])->orderBy('id')->get()->map(fn (SucursalInventario $s): array => $this->sucursal($s)),
            'almacenes' => $acceso->almacenes(AlmacenInventario::query())->orderBy('id')->get()->map(fn (AlmacenInventario $a): array => $this->almacen($a)),
            'dispositivos' => Dispositivo::with('usuario')->when($ids !== null, fn (Builder $q) => $q->whereIn('sucursal_id', $sucursalIds)->where('usuario_id', auth('contable')->id()))->orderBy('id')->get()->map(fn (Dispositivo $d): array => $this->dispositivo($d)),
        ]]);
    }

    public function storeSucursal(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:150'], 'direccion' => ['required', 'string', 'max:250']]);
        $sucursal = SucursalInventario::create([...$datos, 'codigo' => 'SUC-'.Str::upper(Str::random(20)), 'activo' => true]);

        return response()->json(['data' => $this->sucursal($sucursal->loadCount('almacenes'))], 201);
    }

    public function storeAlmacen(Request $request): JsonResponse
    {
        $this->normalizarCodigo($request);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'codigo' => ['required', 'string', 'max:30', Rule::unique('almacenes_inventario', 'codigo')],
            'sucursalId' => ['required', 'integer', Rule::exists('sucursales_inventario', 'id')->where('activo', true)],
            'tiposItem' => ['required', 'array', 'min:1', 'max:3'],
            'tiposItem.*' => ['required', 'string', 'distinct', Rule::in(['Productos', 'Insumos', 'Servicios'])],
        ]);
        $almacen = AlmacenInventario::create([
            'nombre' => $datos['nombre'], 'codigo' => $datos['codigo'], 'sucursal_id' => $datos['sucursalId'],
            'tipos_item' => $datos['tiposItem'], 'activo' => true,
        ]);

        return response()->json(['data' => $this->almacen($almacen)], 201);
    }

    public function storeDispositivo(Request $request): JsonResponse
    {
        $this->normalizarCodigo($request);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'], 'codigo' => ['required', 'string', 'max:50', Rule::unique('dispositivos', 'codigo')],
            'tipo' => ['required', 'string', 'max:80'], 'area' => ['nullable', 'string', 'max:120'],
            'sucursalId' => ['required', 'integer', Rule::exists('sucursales_inventario', 'id')->where('activo', true)],
            'uso' => ['required', Rule::in(['Institucional', 'Personal'])],
            'usuarioId' => ['prohibited'], 'usuario_id' => ['prohibited'], 'vinculadoA' => ['prohibited'],
        ]);
        $dispositivo = Dispositivo::create([
            'nombre' => $datos['nombre'], 'codigo' => $datos['codigo'], 'tipo' => $datos['tipo'],
            'sucursal_id' => $datos['sucursalId'], 'area' => $datos['area'] ?? null, 'uso' => $datos['uso'],
            'usuario_id' => $request->user('contable')->getKey(), 'activo' => true,
        ]);

        return response()->json(['data' => $this->dispositivo($dispositivo->load('usuario'))], 201);
    }

    private function normalizarCodigo(Request $request): void
    {
        if (is_string($request->input('codigo'))) {
            $request->merge(['codigo' => Str::upper(trim($request->input('codigo')))]);
        }
    }

    private function sucursal(SucursalInventario $s): array
    {
        return ['id' => $s->id, 'nombre' => $s->nombre, 'codigo' => $s->codigo, 'direccion' => $s->direccion ?? '',
            'almacenes' => $s->almacenes_count, 'estado' => $s->activo ? 'Activa' : 'Inactiva'];
    }

    private function almacen(AlmacenInventario $a): array
    {
        return ['id' => $a->id, 'nombre' => $a->nombre, 'codigo' => $a->codigo, 'sucursalId' => $a->sucursal_id,
            'tiposItem' => $a->tipos_item, 'estado' => $a->activo ? 'Activo' : 'Inactivo'];
    }

    private function dispositivo(Dispositivo $d): array
    {
        return ['id' => $d->id, 'nombre' => $d->nombre, 'codigo' => $d->codigo, 'tipo' => $d->tipo,
            'sucursalId' => $d->sucursal_id, 'area' => $d->area ?? 'Sin área asignada', 'uso' => $d->uso,
            'vinculadoA' => trim($d->usuario->nombres.' '.($d->usuario->apellidos ?? '')), 'estado' => $d->activo ? 'Activo' : 'Inactivo'];
    }
}
