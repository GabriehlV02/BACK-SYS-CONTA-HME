<?php

namespace App\Flows\Inventario\Http;

use App\Http\Controllers\Controller;
use App\Models\Servicio;
use Illuminate\Http\JsonResponse;

class ServiciosController extends Controller
{
    private const RELACIONES = ['tipo', 'categoria', 'subcategoria', 'unidad', 'clasificacion'];

    public function index(): JsonResponse
    {
        return response()->json(['data' => Servicio::with(self::RELACIONES)->orderBy('nombre')->orderBy('id')->get()->map($this->datos(...))]);
    }

    public function store(GuardarServicioRequest $request): JsonResponse
    {
        $servicio = Servicio::create($request->datosServicio());

        return response()->json(['data' => $this->datos($servicio->load(self::RELACIONES))], 201);
    }

    public function update(GuardarServicioRequest $request, Servicio $servicio): JsonResponse
    {
        $servicio->update($request->datosServicio());

        return response()->json(['data' => $this->datos($servicio->load(self::RELACIONES))]);
    }

    private function datos(Servicio $servicio): array
    {
        return [
            'id' => $servicio->id, 'codigo' => 'SRV-'.str_pad((string) $servicio->id, 6, '0', STR_PAD_LEFT),
            'nombre' => $servicio->nombre, 'descripcion' => $servicio->descripcion,
            'tipo' => 'Servicio', 'tipoOpcion' => $servicio->tipo->clave,
            'categoria' => $servicio->categoria->clave, 'grupo' => $servicio->subcategoria->clave,
            'unidadMedida' => $servicio->unidad->clave, 'clasificacion' => $servicio->clasificacion?->clave ?? '',
            'precioVenta' => $servicio->precio_venta, 'estado' => $servicio->estado, 'stock' => 0,
            'numeroSerie' => $servicio->numero_serie ?? '', 'usarNumeroSerie' => filled($servicio->numero_serie),
            'imagen' => $servicio->imagen,
        ];
    }
}
