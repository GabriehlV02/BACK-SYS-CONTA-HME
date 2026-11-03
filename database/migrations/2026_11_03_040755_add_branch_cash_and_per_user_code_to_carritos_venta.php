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
        Schema::table('carritos_venta', function (Blueprint $table): void {
            $table->dropUnique('carritos_venta_codigo_unique');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales_inventario')->nullOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->unique(['usuario_sistema_id', 'codigo'], 'carritos_venta_usuario_codigo_unique');
            $table->index(['sucursal_id', 'cobrada_en'], 'carritos_venta_sucursal_fecha_index');
            $table->index(['caja_id', 'cobrada_en'], 'carritos_venta_caja_fecha_index');
            $table->index(['usuario_sistema_id', 'cobrada_en'], 'carritos_venta_usuario_fecha_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carritos_venta', function (Blueprint $table): void {
            $table->dropUnique('carritos_venta_usuario_codigo_unique');
            $table->dropIndex('carritos_venta_sucursal_fecha_index');
            $table->dropIndex('carritos_venta_caja_fecha_index');
            $table->dropIndex('carritos_venta_usuario_fecha_index');
            $table->dropForeign(['sucursal_id']);
            $table->dropForeign(['caja_id']);
            $table->dropColumn(['sucursal_id', 'caja_id']);
            $table->unique('codigo');
        });
    }
};
