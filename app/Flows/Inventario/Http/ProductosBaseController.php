<?php

namespace App\Flows\Inventario\Http;

use App\Http\Controllers\Controller;
use App\Models\ProductoBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductosBaseController extends Controller
{
    private const RELACIONES = ['tipo:id,nombre,activo', 'categoria:id,nombre,activo', 'subcategoria:id,nombre,activo', 'clasificacion:id,nombre,activo'];

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:150'],
            'categoria_id' => ['nullable', 'uuid'],
            'estado' => ['nullable', 'in:ACTIVO,INACTIVO'],
            'orden' => ['nullable', 'in:nombre,id,estado'],
            'direccion' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = ProductoBase::query()->with(self::RELACIONES);
        if (! empty($datos['buscar'])) {
            $buscar = $datos['buscar'];
            $query->where(function (Builder $query) use ($buscar): void {
                $query->whereLike('nombre', '%'.$buscar.'%')->orWhereLike('descripcion', '%'.$buscar.'%');
                if (preg_match('/^PRD-0*([1-9][0-9]{0,14})$/i', $buscar, $coincidencia)) {
                    $query->orWhere('id', $coincidencia[1]);
                }
            });
        }
        foreach (['categoria_id', 'estado'] as $filtro) {
            if (! empty($datos[$filtro])) {
                $query->where($filtro, $datos[$filtro]);
            }
        }
        $orden = $datos['orden'] ?? 'nombre';
        $query->orderBy($orden, $datos['direccion'] ?? 'asc');
        if ($orden !== 'id') {
            $query->orderBy('id');
        }

        return response()->json($query->paginate($datos['por_pagina'] ?? 20));
    }

    public function store(GuardarProductoBaseRequest $request): JsonResponse
    {
        $producto = ProductoBase::create($request->validated());

        return response()->json(['data' => $producto->load(self::RELACIONES)], 201);
    }

    public function update(GuardarProductoBaseRequest $request, ProductoBase $producto): JsonResponse
    {
        $producto->update($request->validated());

        return response()->json(['data' => $producto->load(self::RELACIONES)]);
    }
}
