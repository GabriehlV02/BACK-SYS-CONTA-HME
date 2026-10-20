<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('clave', 150)->unique();
            $table->text('descripcion');
            $table->foreignUuid('tipo_id')->constrained('tipos_inventario')->restrictOnDelete();
            $table->foreignUuid('categoria_id')->constrained('categorias_inventario')->restrictOnDelete();
            $table->foreignUuid('subcategoria_id')->constrained('subcategorias_inventario')->restrictOnDelete();
            $table->foreignUuid('unidad_id')->constrained('unidades_inventario')->restrictOnDelete();
            $table->foreignUuid('clasificacion_id')->nullable()->constrained('clasificaciones_inventario')->restrictOnDelete();
            $table->decimal('precio_venta', 12, 2);
            $table->string('estado', 10)->default('ACTIVO');
            $table->string('numero_serie', 100)->nullable();
            $table->text('imagen')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios');
    }
};
