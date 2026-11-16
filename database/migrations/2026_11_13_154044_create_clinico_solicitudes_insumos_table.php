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
        Schema::create('clinico_solicitudes_insumos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('solicitado_por');
            $table->string('solicitante', 200);
            $table->foreignId('almacen_destino_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->json('detalles');
            $table->string('observacion', 1000)->nullable();
            $table->foreignId('almacen_origen_id')->nullable()->constrained('almacenes_inventario')->restrictOnDelete();
            $table->uuid('dispensado_por')->nullable();
            $table->timestamp('dispensado_en')->nullable();
            $table->json('movimientos')->nullable();
            $table->timestamps();
            $table->index(['almacen_destino_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinico_solicitudes_insumos');
    }
};
