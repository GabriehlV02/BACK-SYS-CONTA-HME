<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150);
            $table->foreignId('sucursal_id')->constrained('sucursales_inventario')->restrictOnDelete();
            $table->foreignUuid('creado_por')->nullable()->constrained('usuarios_sistema')->nullOnDelete();
            $table->json('tipos_item');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index('sucursal_id');
        });
        Schema::create('almacen_caja', function (Blueprint $table): void {
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->primary(['caja_id', 'almacen_id']);
            $table->index('almacen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen_caja');
        Schema::dropIfExists('cajas');
    }
};
