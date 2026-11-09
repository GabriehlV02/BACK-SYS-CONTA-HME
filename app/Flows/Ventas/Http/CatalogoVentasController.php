<?php

namespace App\Flows\Ventas\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Models\AlmacenInventario;
use App\Models\Caja;
use App\Models\ExistenciaLote;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CatalogoVentasController
{
    public function __invoke(AccesoAlmacenes $acceso): JsonResponse
    {
        $usuario = Auth::guard('contable')->user();
        abort_unless($usuario, 401);
        $esAdministrador = $usuario->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->exists();
        $almacenesPermitidos = $acceso->ids();
        $cajas = Caja::query()->with(['sucursal:id,nombre', 'almacenes' => function ($query) use ($almacenesPermitidos): void {
            $query->where('almacenes_inventario.activo', true)
                ->when($almacenesPermitidos !== null, fn (Builder $q) => $q->whereIn('almacenes_inventario.id', $almacenesPermitidos));
        }])->where('activo', true)
            ->whereHas('sucursal', fn (Builder $query) => $query->where('activo', true))
            ->whereHas('almacenes', fn (Builder $query) => $query->where('almacenes_inventario.activo', true)
                ->when($almacenesPermitidos !== null, fn (Builder $q) => $q->whereIn('almacenes_inventario.id', $almacenesPermitidos)))
            ->when(! $esAdministrador, function (Builder $query) use ($usuario): void {
                $query->whereHas('usuarios', fn (Builder $cajasUsuario) => $cajasUsuario->whereKey($usuario->id))
                    ->whereExists(fn ($sucursalesUsuario) => $sucursalesUsuario->selectRaw('1')->from('sucursal_usuario')
                        ->whereColumn('sucursal_usuario.sucursal_id', 'cajas.sucursal_id')
                        ->where('sucursal_usuario.usuario_id', $usuario->id));
            })->orderBy('sucursal_id')->orderBy('nombre')->get();
        $almacenIds = $cajas->flatMap(fn (Caja $caja) => $caja->almacenes->modelKeys())->unique()->values();
        $almacenes = AlmacenInventario::query()->with('sucursal')->whereIn('id', $almacenIds)->orderBy('nombre')->get();
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
            ->whereHas('tipo', fn (Builder $q) => $q->where('activo', true))->where(fn (Builder $q) => $q->whereNull('unidad_id')->orWhereHas('unidad', fn (Builder $unidad) => $unidad->where('activo', true)))
            ->orderBy('nombre')->get()->map(fn (Servicio $s): array => [
                'id' => 'servicio-'.$s->id, 'codigo' => 'SRV-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT), 'nombre' => $s->nombre, 'tipo' => 'Servicio',
                'categoria' => $s->categoria?->nombre ?? '', 'precio' => (float) $s->precio_venta,
            ]);

        return response()->json(['data' => $productos->concat($servicios)->values(), 'almacenes' => $almacenes->map(fn (AlmacenInventario $a): array => [
            'id' => $a->id, 'nombre' => $a->sucursal->nombre.' - '.$a->nombre,
        ]), 'cajas' => $cajas->map(fn (Caja $caja): array => [
            'id' => $caja->id, 'nombre' => $caja->nombre, 'codigo' => $caja->codigo,
            'sucursalId' => $caja->sucursal_id, 'sucursal' => $caja->sucursal->nombre,
            'almacenIds' => $caja->almacenes->modelKeys(),
        ])->values()]);
    }
}
