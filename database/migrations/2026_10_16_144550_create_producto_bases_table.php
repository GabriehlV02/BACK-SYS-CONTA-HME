<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_base', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('clave', 150)->unique();
            $table->text('descripcion')->nullable();
            $table->foreignUuid('tipo_id')->constrained('tipos_inventario')->restrictOnDelete();
            $table->foreignUuid('categoria_id')->nullable()->constrained('categorias_inventario')->restrictOnDelete();
            $table->foreignUuid('subcategoria_id')->nullable()->constrained('subcategorias_inventario')->restrictOnDelete();
            $table->foreignUuid('clasificacion_id')->nullable()->constrained('clasificaciones_inventario')->restrictOnDelete();
            $table->string('estado', 10)->default('ACTIVO');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_base');
    }
};
