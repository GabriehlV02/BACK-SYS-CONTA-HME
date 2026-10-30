<?php

namespace App\Flows\Ventas\Http;

use App\Models\CarritoVenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentasController
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
        ]);

        $ventas = CarritoVenta::query()
            ->with([
                'cliente:id,paciente_id,nit_ci,nombre_razon_social,telefono,correo',
                'detalles:id,carrito_venta_id,codigo_item,nombre_item,tipo_item,cantidad,precio_unitario,descuento,total',
            ])
            ->where('estado', 'COMPLETADA')
            ->when($datos['fecha_desde'] ?? null, function (Builder $query, string $fechaDesde): void {
                $query->whereDate('cobrada_en', '>=', $fechaDesde);
            })
            ->when($datos['fecha_hasta'] ?? null, function (Builder $query, string $fechaHasta): void {
                $query->whereDate('cobrada_en', '<=', $fechaHasta);
            })
            ->orderByDesc('cobrada_en')
            ->get();

        return response()->json(['data' => $ventas]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'uuid', 'exists:clientes,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.codigo_item' => ['nullable', 'string', 'max:60'],
            'detalles.*.nombre_item' => ['required', 'string', 'max:180'],
            'detalles.*.tipo_item' => ['nullable', 'string', 'max:30'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $venta = DB::transaction(function () use ($datos): CarritoVenta {
            $subtotal = collect($datos['detalles'])->sum(
                fn (array $detalle): float => round((float) $detalle['cantidad'] * (float) $detalle['precio_unitario'], 2),
            );
            $descuento = min((float) ($datos['descuento'] ?? 0), $subtotal);

            $venta = CarritoVenta::query()->create([
                'codigo' => $this->siguienteCodigo(),
                'cliente_id' => $datos['cliente_id'],
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

    private function siguienteCodigo(): string
    {
        $ultimoCorrelativo = CarritoVenta::query()
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
