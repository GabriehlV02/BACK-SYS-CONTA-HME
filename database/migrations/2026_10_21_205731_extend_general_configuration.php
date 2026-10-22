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
        Schema::table('sucursales_inventario', function (Blueprint $table): void {
            $table->string('direccion', 250)->nullable();
        });
        Schema::table('almacenes_inventario', function (Blueprint $table): void {
            $table->json('tipos_item')->default('["Productos","Insumos"]');
        });
        Schema::create('dispositivos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 80);
            $table->foreignId('sucursal_id')->constrained('sucursales_inventario')->restrictOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->restrictOnDelete();
            $table->string('area', 120)->nullable();
            $table->string('uso', 20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('dispositivos');
        Schema::table('almacenes_inventario', fn (Blueprint $table) => $table->dropColumn('tipos_item'));
        Schema::table('sucursales_inventario', fn (Blueprint $table) => $table->dropColumn('direccion'));
    }
};
