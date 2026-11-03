<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_almacen_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items_inventario')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->cascadeOnDelete();
            $table->string('ubicacion', 120);
            $table->timestamps();
            $table->unique(['item_id', 'almacen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_almacen_ubicaciones');
    }
};
