<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_clinicas_solicitudes', function (Blueprint $table): void {
            $table->uuid('solicitud_id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->restrictOnDelete();
            $table->foreignUuid('venta_id')->constrained('carritos_venta')->restrictOnDelete();
            $table->string('huella', 64);
            $table->json('pagos');
            $table->decimal('cambio', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('ventas_clinicas_salidas', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('venta_id')->constrained('carritos_venta')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('lotes_items')->restrictOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_clinicas_salidas');
        Schema::dropIfExists('ventas_clinicas_solicitudes');
    }
};
