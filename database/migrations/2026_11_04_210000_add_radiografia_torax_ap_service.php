<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $tipo = DB::table('tipos_inventario')->where('clave', 'SERVICIO')->first();
        if (! $tipo) { $id = (string) Str::uuid(); DB::table('tipos_inventario')->insert(['id' => $id, 'nombre' => 'Servicio', 'clave' => 'SERVICIO', 'activo' => true, 'tipo_base' => 'SERVICIO', 'created_at' => $ahora, 'updated_at' => $ahora]); $tipo = (object) ['id' => $id]; }
        $categoria = DB::table('categorias_inventario')->where('clave', 'IMAGENOLOGIA')->first();
        if (! $categoria) { $id = (string) Str::uuid(); DB::table('categorias_inventario')->insert(['id' => $id, 'nombre' => 'Imagenología', 'clave' => 'IMAGENOLOGIA', 'activo' => true, 'prefijo' => 'IMA', 'created_at' => $ahora, 'updated_at' => $ahora]); $categoria = (object) ['id' => $id]; }
        $subcategoria = DB::table('subcategorias_inventario')->where('categoria_id', $categoria->id)->where('clave', 'RADIOGRAFIA')->first();
        if (! $subcategoria) { $id = (string) Str::uuid(); DB::table('subcategorias_inventario')->insert(['id' => $id, 'categoria_id' => $categoria->id, 'nombre' => 'Radiografía', 'clave' => 'RADIOGRAFIA', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]); $subcategoria = (object) ['id' => $id]; }
        $unidad = DB::table('unidades_inventario')->where('clave', 'UNIDAD')->first();
        if (! $unidad) { $id = (string) Str::uuid(); DB::table('unidades_inventario')->insert(['id' => $id, 'nombre' => 'Unidad', 'clave' => 'UNIDAD', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]); $unidad = (object) ['id' => $id]; }
        DB::table('servicios')->updateOrInsert(['clave' => 'RADIOGRAFIA-TORAX-AP'], ['nombre' => 'Radiografía de tórax AP', 'descripcion' => 'Radiografía de tórax en proyección anteroposterior.', 'tipo_id' => $tipo->id, 'categoria_id' => $categoria->id, 'subcategoria_id' => $subcategoria->id, 'unidad_id' => $unidad->id, 'clasificacion_id' => null, 'precio_venta' => 120, 'estado' => 'ACTIVO', 'updated_at' => $ahora, 'created_at' => $ahora]);
    }

    public function down(): void { DB::table('servicios')->where('clave', 'RADIOGRAFIA-TORAX-AP')->delete(); }
};
