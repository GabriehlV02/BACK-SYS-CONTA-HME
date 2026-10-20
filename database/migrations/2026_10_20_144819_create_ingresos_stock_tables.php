<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos_stock', function (Blueprint $table): void {
            $table->id();
            $table->uuid('solicitud_id')->unique();
            $table->string('huella', 64);
            $table->date('fecha')->index();
            $table->string('proveedor', 200);
            $table->string('comprobante', 100)->nullable();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->string('almacen_nombre', 320);
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->restrictOnDelete();
            $table->decimal('total', 18, 2);
            $table->timestamps();
        });
        Schema::create('detalles_ingreso_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingreso_id')->constrained('ingresos_stock')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('lotes_items')->restrictOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('subtotal', 18, 2);
            $table->json('datos');
            $table->unique(['ingreso_id', 'lote_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_ingreso_stock');
        Schema::dropIfExists('ingresos_stock');
    }
};
