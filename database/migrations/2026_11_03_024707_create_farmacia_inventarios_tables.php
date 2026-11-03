<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farmacia_inventarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales_inventario')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->string('estado', 16)->default('EN_CURSO');
            $table->timestamp('iniciado_en');
            $table->timestamp('finalizado_en')->nullable();
            $table->uuid('iniciado_por')->nullable();
            $table->timestamps();
            $table->index(['sucursal_id', 'iniciado_en']);
            $table->index(['almacen_id', 'estado']);
        });

        Schema::create('farmacia_inventario_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventario_id')->constrained('farmacia_inventarios')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items_inventario')->restrictOnDelete();
            $table->decimal('cantidad_esperada', 18, 3)->default(0);
            $table->decimal('cantidad_contada', 18, 3)->nullable();
            $table->timestamp('contado_en')->nullable();
            $table->timestamps();
            $table->unique(['inventario_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmacia_inventario_detalles');
        Schema::dropIfExists('farmacia_inventarios');
    }
};
