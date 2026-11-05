<?php

namespace App\Flows\Ventas\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Flows\Usuarios\Models\UsuarioSistema;
use App\Models\Caja;
use App\Models\CarritoVenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VentasController
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'sucursal_id' => ['nullable', 'integer'],
            'caja_id' => ['nullable', 'integer'],
            'usuario_id' => ['nullable', 'uuid'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ventas = CarritoVenta::query()
            ->with([
                'cliente:id,paciente_id,nit_ci,nombre_razon_social,telefono,correo',
                'detalles:id,carrito_venta_id,codigo_item,nombre_item,tipo_item,cantidad,precio_unitario,descuento,total',
                'sucursal:id,nombre', 'caja:id,nombre,codigo', 'usuarioSistema:id,nombres,apellidos,usuario',
            ])
            ->where('estado', 'COMPLETADA')
            ->when($datos['fecha_desde'] ?? null, function (Builder $query, string $fechaDesde): void {
                $query->whereDate('cobrada_en', '>=', $fechaDesde);
            })
            ->when($datos['fecha_hasta'] ?? null, function (Builder $query, string $fechaHasta): void {
                $query->whereDate('cobrada_en', '<=', $fechaHasta);
            })
            ->when($datos['sucursal_id'] ?? null, fn (Builder $query, int $id) => $query->where('sucursal_id', $id))
            ->when($datos['caja_id'] ?? null, fn (Builder $query, int $id) => $query->where('caja_id', $id))
            ->when($datos['usuario_id'] ?? null, fn (Builder $query, string $id) => $query->where('usuario_sistema_id', $id))
            ->when($datos['buscar'] ?? null, function (Builder $query, string $buscar): void {
                $query->where(function (Builder $filtro) use ($buscar): void {
                    $filtro->where('codigo', 'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn (Builder $cliente) => $cliente->where('nombre_razon_social', 'like', "%{$buscar}%"))
                        ->orWhereHas('usuarioSistema', fn (Builder $usuario) => $usuario->where('nombres', 'like', "%{$buscar}%")->orWhere('apellidos', 'like', "%{$buscar}%"));
                });
            })
            ->orderByDesc('cobrada_en')
            ->orderByDesc('id')
            ->paginate($datos['per_page'] ?? 50);

        $pagos = Schema::hasTable('ventas_clinicas_solicitudes')
            ? DB::table('ventas_clinicas_solicitudes')->whereIn('venta_id', $ventas->getCollection()->pluck('id'))->get(['venta_id', 'pagos', 'cambio'])->keyBy('venta_id')
            : collect();
        $ventas->getCollection()->each(function (CarritoVenta $venta) use ($pagos): void {
            $registro = $pagos->get($venta->id);
            $venta->setAttribute('pagos', $registro ? json_decode($registro->pagos, true, 512, JSON_THROW_ON_ERROR) : null);
            $venta->setAttribute('cambio', $registro?->cambio);
        });

        return response()->json(['data' => $ventas->items(), 'current_page' => $ventas->currentPage(), 'last_page' => $ventas->lastPage(), 'total' => $ventas->total()]);
    }

    public function store(Request $request, AccesoAlmacenes $acceso): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'caja_id' => ['required', 'integer', 'exists:cajas,id'],
            'almacen_id' => ['nullable', 'integer', 'exists:almacenes_inventario,id'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.codigo_item' => ['nullable', 'string', 'max:60'],
            'detalles.*.nombre_item' => ['required', 'string', 'max:180'],
            'detalles.*.tipo_item' => ['nullable', 'string', 'max:30'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);
        /** @var UsuarioSistema $usuario */
        $usuario = $request->user('contable');
        abort_unless($usuario, 401);
        $administrador = $usuario->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->exists();
        $caja = Caja::query()->with('sucursal')->whereKey($datos['caja_id'])->where('activo', true)->firstOrFail();
        abort_unless($caja->sucursal && $caja->sucursal->activo, 422, 'La caja no tiene una sucursal activa.');
        if (! $administrador) {
            abort_unless($usuario->cajasAsignadas()->whereKey($caja->id)->exists(), 403, 'No tienes acceso a esta caja.');
            abort_unless($usuario->sucursalesAsignadas()->whereKey($caja->sucursal_id)->exists(), 403, 'No tienes acceso a esta sucursal.');
        }
        $requiereAlmacen = collect($datos['detalles'])->contains(fn (array $detalle): bool => ! in_array(mb_strtolower($detalle['tipo_item'] ?? ''), ['servicio'], true));
        if ($requiereAlmacen && empty($datos['almacen_id'])) {
            throw ValidationException::withMessages(['almacen_id' => 'Selecciona el almacén desde donde salen los productos.']);
        }
        if (! empty($datos['almacen_id'])) {
            $acceso->exigir((int) $datos['almacen_id']);
            abort_unless($caja->almacenes()->whereKey($datos['almacen_id'])->where('almacenes_inventario.activo', true)->exists(), 422, 'El almacén no está asociado a esta caja.');
        }

        $venta = DB::transaction(function () use ($datos, $usuario, $caja): CarritoVenta {
            UsuarioSistema::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            $subtotal = collect($datos['detalles'])->sum(
                fn (array $detalle): float => round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2),
            );
            $descuento = min((float) ($datos['descuento'] ?? 0), $subtotal);

            $venta = CarritoVenta::query()->create([
                'codigo' => $this->siguienteCodigo($usuario->id),
                'cliente_id' => $datos['cliente_id'],
                'usuario_sistema_id' => $usuario->id,
                'sucursal_id' => $caja->sucursal_id,
                'caja_id' => $caja->id,
                'estado' => 'COMPLETADA',
                'moneda' => 'BOB',
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $subtotal - $descuento,
                'observaciones' => $datos['observaciones'] ?? null,
                'cobrada_en' => now(),
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $totalDetalle = round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2);
                $venta->detalles()->create([
                    'codigo_item' => $detalle['codigo_item'] ?? null,
                    'nombre_item' => $detalle['nombre_item'],
                    'tipo_item' => $detalle['tipo_item'] ?? null,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => 0,
                    'total' => $totalDetalle,
                ]);
            }

            return $venta->load('cliente', 'detalles');
        });

        return response()->json(['data' => $venta], 201);
    }

    private function siguienteCodigo(string $usuarioId): string
    {
        $ultimoCorrelativo = CarritoVenta::query()
            ->where('usuario_sistema_id', $usuarioId)
            ->lockForUpdate()
            ->pluck('codigo')
            ->reduce(function (int $mayor, string $codigo): int {
                if (preg_match('/^VTA-(\d+)$/', $codigo, $coincidencia) !== 1) {
                    return $mayor;
                }

                return max($mayor, (int) $coincidencia[1]);
            }, 0);

        return 'VTA-'.str_pad((string) ($ultimoCorrelativo + 1), 8, '0', STR_PAD_LEFT);
    }
}
