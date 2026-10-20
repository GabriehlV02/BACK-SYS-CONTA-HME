<?php

namespace App\Flows\Inventario;

use App\Models\AlmacenInventario;
use App\Models\ExistenciaLote;
use App\Models\IngresoStock;
use App\Models\ItemInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrarIngreso
{
    public function handle(array $datos, string $usuarioId): IngresoStock
    {
        return DB::transaction(function () use ($datos, $usuarioId): IngresoStock {
            $almacen = AlmacenInventario::with('sucursal')->lockForUpdate()->findOrFail($datos['almacen_id']);
            $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
            $ingreso = IngresoStock::query()->createOrFirst(['solicitud_id' => $datos['solicitud_id']], [
                'huella' => $huella, 'fecha' => $datos['fecha'], 'proveedor' => $datos['proveedor'],
                'comprobante' => $datos['comprobante'] ?? null, 'almacen_id' => $almacen->id,
                'almacen_nombre' => $almacen->sucursal->nombre.' - '.$almacen->nombre,
                'usuario_id' => $usuarioId, 'total' => '0.00',
            ]);
            if (! $ingreso->wasRecentlyCreated) {
                abort_unless($ingreso->huella === $huella && $ingreso->usuario_id === $usuarioId, 409, 'Esta solicitud ya se usó para otro ingreso. Abre un nuevo registro.');

                return $ingreso->load('detalles');
            }
            if (! $almacen->activo || ! $almacen->sucursal->activo) {
                throw ValidationException::withMessages(['almacen_id' => 'El almacén y su sucursal deben estar activos.']);
            }
            $items = ItemInventario::with(['productoBase.tipo', 'productoBase.categoria', 'unidad'])
                ->whereIn('id', array_column($datos['lineas'], 'item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $totalCentavos = 0;
            $lotesUsados = [];
            foreach ($datos['lineas'] as $indice => $linea) {
                $item = $items->get($linea['item_id']);
                if (! $item || $item->estado !== 'ACTIVO' || $item->productoBase->estado !== 'ACTIVO'
                    || ! $item->productoBase->tipo->activo || ! in_array($item->productoBase->tipo->tipo_base, ['PRODUCTO', 'INSUMO']) || ! $item->unidad->activo) {
                    throw ValidationException::withMessages(["lineas.$indice.item_id" => 'Selecciona un ítem activo con agrupador, tipo y unidad activos.']);
                }
                $numero = Str::squish($linea['lote']);
                if ($numero === '') {
                    throw ValidationException::withMessages(["lineas.$indice.lote" => 'Indica el número de lote.']);
                }
                $lote = $item->lotes()->where('clave', Str::upper(Str::ascii($numero)))->lockForUpdate()->first();
                $vence = $linea['vence'] ?? null;
                if ($lote && $lote->vencimiento?->format('Y-m-d') !== $vence) {
                    throw ValidationException::withMessages(["lineas.$indice.vence" => 'El vencimiento debe coincidir con el lote ya registrado.']);
                }
                $precios = ['precio_1' => $linea['precio1'], 'precio_2' => $linea['precio2'], 'precio_3' => $linea['precio3']];
                if (! $lote) {
                    $lote = $item->lotes()->create(['numero' => $numero, 'clave' => Str::upper(Str::ascii($numero)), 'vencimiento' => $vence, ...$precios]);
                } else {
                    $lote->update($precios);
                }
                if (in_array($lote->id, $lotesUsados, true)) {
                    throw ValidationException::withMessages(["lineas.$indice.lote" => 'El mismo ítem y lote solo puede aparecer una vez por ingreso.']);
                }
                $lotesUsados[] = $lote->id;
                $cantidad = $this->escalado($linea['cantidad'], 3);
                $costo = $this->escalado($linea['costoUnitario'], 4);
                $subtotal = intdiv($cantidad * $costo + 50000, 100000);
                $totalCentavos += $subtotal;
                $ingreso->detalles()->create([
                    'lote_id' => $lote->id, 'cantidad' => $linea['cantidad'], 'costo_unitario' => $linea['costoUnitario'], 'subtotal' => $this->moneda($subtotal),
                    'datos' => ['itemId' => $item->id, 'codigo' => $item->codigo, 'producto' => $item->nombre_comercial,
                        'generico' => $item->productoBase->nombre, 'categoria' => $item->productoBase->categoria?->nombre ?? 'Sin categoría',
                        'marca' => $item->marca, 'lote' => $lote->numero, 'unidad' => $item->unidad->nombre,
                        'vence' => $vence ?? '', 'observacion' => $linea['observacion'] ?? '',
                        'precio1' => $lote->precio_1, 'precio2' => $lote->precio_2, 'precio3' => $lote->precio_3],
                ]);
                $existencia = ExistenciaLote::firstOrCreate(['lote_id' => $lote->id, 'almacen_id' => $almacen->id], ['cantidad' => '0.000']);
                $existencia->increment('cantidad', $linea['cantidad'], ['costo_unitario' => $linea['costoUnitario'], 'fecha_ingreso' => $datos['fecha']]);
            }
            $ingreso->update(['total' => $this->moneda($totalCentavos)]);

            return $ingreso->load('detalles');
        }, 3);
    }

    private function escalado(string|int|float $valor, int $decimales): int
    {
        $partes = explode('.', (string) $valor);

        return ((int) $partes[0]) * (10 ** $decimales) + (int) str_pad($partes[1] ?? '', $decimales, '0');
    }

    private function moneda(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
