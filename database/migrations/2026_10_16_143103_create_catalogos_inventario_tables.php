<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tipos_inventario', 'unidades_inventario', 'categorias_inventario', 'subcategorias_inventario', 'clasificaciones_inventario'] as $nombre) {
            Schema::create($nombre, function (Blueprint $table) use ($nombre): void {
                $table->uuid('id')->primary();
                $table->string('nombre', 100);
                $table->string('clave', 100);
                $table->boolean('activo')->default(true);
                if ($nombre === 'tipos_inventario') {
                    $table->text('descripcion')->nullable();
                    $table->string('tipo_base', 15);
                }
                if ($nombre === 'categorias_inventario') {
                    $table->string('prefijo', 10)->unique();
                }
                if ($nombre === 'subcategorias_inventario') {
                    $table->foreignUuid('categoria_id')->constrained('categorias_inventario')->restrictOnDelete();
                    $table->unique(['categoria_id', 'clave']);
                } else {
                    $table->unique('clave');
                }
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['subcategorias_inventario', 'categorias_inventario', 'tipos_inventario', 'unidades_inventario', 'clasificaciones_inventario'] as $nombre) {
            Schema::dropIfExists($nombre);
        }
    }
};
