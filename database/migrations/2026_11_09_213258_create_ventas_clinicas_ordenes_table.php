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
        Schema::create('ventas_clinicas_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('venta_detalle_id')->constrained('carrito_venta_detalles')->restrictOnDelete();
            $table->unsignedInteger('unidad');
            $table->uuid('paciente_id');
            $table->string('tipo', 30);
            $table->string('nombre', 180);
            $table->timestamp('procesada_en')->nullable();
            $table->unique(['venta_detalle_id', 'unidad'], 'venta_orden_detalle_unidad_unique');
            $table->index('procesada_en');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas_clinicas_ordenes');
    }
};
