<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carritos_venta', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 40)->unique();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('usuario_sistema_id')->nullable()->constrained('usuarios_sistema')->nullOnDelete();
            $table->string('estado', 20)->default('BORRADOR');
            $table->string('moneda', 3)->default('BOB');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamp('cobrada_en')->nullable();
            $table->timestamp('anulada_en')->nullable();
            $table->timestamps();
        });

        Schema::create('carrito_venta_detalles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('carrito_venta_id')->constrained('carritos_venta')->cascadeOnDelete();
            // item_id apunta lógicamente al catálogo compartido, aún no implementado en Contable.
            $table->uuid('item_id')->nullable()->index();
            $table->string('codigo_item', 60)->nullable();
            $table->string('nombre_item', 180);
            $table->string('tipo_item', 30)->nullable();
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrito_venta_detalles');
        Schema::dropIfExists('carritos_venta');
    }
};
