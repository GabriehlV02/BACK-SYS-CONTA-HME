<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farmacia_espacios')) {
            Schema::create('farmacia_espacios', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('almacen_id')->constrained('almacenes_inventario')->cascadeOnDelete();
                $table->string('tipo', 20);
                $table->string('nombre', 120);
                $table->timestamps();
                $table->unique(['almacen_id', 'tipo', 'nombre']);
            });
        }

        if (! Schema::hasTable('farmacia_repisas')) {
            Schema::create('farmacia_repisas', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('espacio_id')->constrained('farmacia_espacios')->cascadeOnDelete();
                $table->string('nombre', 120);
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();
                $table->unique(['espacio_id', 'nombre']);
            });
        }

        if (! Schema::hasColumn('item_almacen_ubicaciones', 'repisa_id')) {
            Schema::table('item_almacen_ubicaciones', function (Blueprint $table): void {
                $table->foreignId('repisa_id')->nullable()->after('ubicacion')->constrained('farmacia_repisas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('item_almacen_ubicaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repisa_id');
        });
        Schema::dropIfExists('farmacia_repisas');
        Schema::dropIfExists('farmacia_espacios');
    }
};
