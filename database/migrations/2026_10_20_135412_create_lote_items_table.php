<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items_inventario')->restrictOnDelete();
            $table->string('numero', 100);
            $table->string('clave', 100);
            $table->date('vencimiento')->nullable();
            $table->decimal('precio_1', 12, 2);
            $table->decimal('precio_2', 12, 2);
            $table->decimal('precio_3', 12, 2);
            $table->unique(['item_id', 'clave']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_items');
    }
};
