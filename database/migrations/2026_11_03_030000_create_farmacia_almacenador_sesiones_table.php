<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmacia_almacenador_sesiones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('usuario_id');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales_inventario')->nullOnDelete();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes_inventario')->nullOnDelete();
            $table->string('estado', 20)->default('ESPERANDO_ALMACEN');
            $table->timestamp('iniciado_en')->nullable();
            $table->timestamps();
            $table->index(['usuario_id', 'estado', 'id'], 'farmacia_almacenador_usuario_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmacia_almacenador_sesiones');
    }
};
