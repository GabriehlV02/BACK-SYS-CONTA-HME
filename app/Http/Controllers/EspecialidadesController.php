<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EspecialidadesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DB::table('especialidades')->orderBy('nombre')->get()->map(fn ($item) => $this->normalizar($item))]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:120']]);
        if (DB::table('especialidades')->whereRaw('LOWER(nombre) = ?', [mb_strtolower($datos['nombre'])])->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Esta especialidad ya está registrada.']);
        }
        $id = DB::table('especialidades')->insertGetId(['nombre' => $datos['nombre'], 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => $this->normalizar(DB::table('especialidades')->find($id))], 201);
    }

    public function update(Request $request, int $especialidad): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        abort_unless(DB::table('especialidades')->where('id', $especialidad)->exists(), 404);
        DB::table('especialidades')->where('id', $especialidad)->update(['activo' => $datos['activo'], 'updated_at' => now()]);

        return response()->json(['data' => $this->normalizar(DB::table('especialidades')->find($especialidad))]);
    }

    private function normalizar(object $item): object
    {
        $item->activo = (bool) $item->activo;

        return $item;
    }
}
