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
        Schema::create('clinico_consumos_inventario', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('emergencia_id');
            $table->uuid('usuario_id');
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items_inventario')->restrictOnDelete();
            $table->decimal('cantidad', 18, 3);
            $table->string('huella', 64);
            $table->json('datos_clinicos');
            $table->json('lotes');
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();
            $table->index(['emergencia_id', 'sincronizado_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinico_consumos_inventario');
    }
};
