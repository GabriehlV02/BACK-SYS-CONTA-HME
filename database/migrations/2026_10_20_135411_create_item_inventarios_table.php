<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_base_id')->constrained('productos_base')->restrictOnDelete();
            $table->string('nombre_comercial', 150);
            $table->string('marca', 100);
            $table->string('presentacion', 150);
            $table->string('clave', 64);
            $table->text('descripcion')->nullable();
            $table->foreignUuid('unidad_id')->constrained('unidades_inventario')->restrictOnDelete();
            $table->string('codigo_barras', 100)->nullable()->unique();
            $table->decimal('precio_1', 12, 2);
            $table->decimal('precio_2', 12, 2);
            $table->decimal('precio_3', 12, 2);
            $table->string('estado', 10)->default('ACTIVO');
            $table->unique(['producto_base_id', 'clave']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_inventario');
    }
};
