<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales_inventario', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre', 150);
            $table->string('codigo', 30)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('almacenes_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales_inventario')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 30)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('existencias_lote', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes_items')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->decimal('cantidad', 18, 3)->default(0);
            $table->decimal('costo_unitario', 14, 4)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->unique(['lote_id', 'almacen_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('existencias_lote');
        Schema::dropIfExists('almacenes_inventario');
        Schema::dropIfExists('sucursales_inventario');
    }
};
