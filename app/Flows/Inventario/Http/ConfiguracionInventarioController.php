<?php

namespace App\Flows\Inventario\Http;

use App\Flows\Inventario\AccesoAlmacenes;
use App\Http\Controllers\Controller;
use App\Models\CategoriaInventario;
use App\Models\ClasificacionInventario;
use App\Models\SubcategoriaInventario;
use App\Models\TipoInventario;
use App\Models\UnidadInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfiguracionInventarioController extends Controller
{
    public function index(): JsonResponse
    {
        $datos = [];
        foreach (GuardarConfiguracionInventarioRequest::MODELOS as $catalogo => $modelo) {
            $query = $modelo::query()->orderBy('nombre');
            if ($catalogo === 'subcategorias') {
                $query->with('categoria:id,nombre,activo');
            }
            if ($catalogo === 'tipos') {
                $query->withCount(['productosBase as cantidad_productos' => fn (Builder $q) => app(AccesoAlmacenes::class)->grupos($q), 'servicios as cantidad_servicios']);
            }
            $registros = $query->get();
            if ($catalogo === 'tipos') {
                $registros->each(function (TipoInventario $tipo): void {
                    $tipo->setAttribute('cantidad', $tipo->cantidad_productos + $tipo->cantidad_servicios);
                    $tipo->makeHidden(['cantidad_productos', 'cantidad_servicios']);
                });
            }
            $datos[$catalogo] = $registros;
        }

        return response()->json(['data' => $datos]);
    }

    public function store(GuardarConfiguracionInventarioRequest $request, string $catalogo): JsonResponse
    {
        $modelo = GuardarConfiguracionInventarioRequest::MODELOS[$catalogo];
        $registro = $modelo::create($request->validated());

        return response()->json(['data' => $registro], 201);
    }

    public function update(Request $request, string $catalogo, string $registro): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $modelo = GuardarConfiguracionInventarioRequest::MODELOS[$catalogo];
        $elemento = $modelo::findOrFail($registro);
        if ($elemento instanceof SubcategoriaInventario && $datos['activo'] && ! $elemento->categoria->activo) {
            throw ValidationException::withMessages(['activo' => 'Activa primero la categoría de esta subcategoría.']);
        }
        $elemento->update($datos);

        return response()->json(['data' => $elemento]);
    }

    public function opciones(): JsonResponse
    {
        $categorias = CategoriaInventario::where('activo', true)
            ->with(['subcategorias' => fn ($query) => $query->where('activo', true)->orderBy('nombre')])->orderBy('nombre')->get();

        return response()->json(['data' => [
            'unidades' => UnidadInventario::where('activo', true)->orderBy('nombre')->pluck('clave'),
            'tipos' => (object) TipoInventario::where('activo', true)->orderBy('nombre')->pluck('tipo_base', 'clave')->all(),
            'categorias' => (object) $categorias->mapWithKeys(fn (CategoriaInventario $categoria): array => [
                $categoria->clave => ['prefijo' => $categoria->prefijo, 'subcategorias' => $categoria->subcategorias->pluck('clave')->all()],
            ])->all(),
            'clasificaciones' => ClasificacionInventario::where('activo', true)->orderBy('nombre')->pluck('clave'),
        ]]);
    }
}
