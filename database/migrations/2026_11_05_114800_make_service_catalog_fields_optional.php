<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table): void {
            $table->uuid('categoria_id')->nullable()->change();
            $table->uuid('subcategoria_id')->nullable()->change();
            $table->uuid('unidad_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('servicios')->whereNull('categoria_id')->orWhereNull('subcategoria_id')->orWhereNull('unidad_id')->exists()) {
            throw new RuntimeException('Asigna categoría, subcategoría y unidad a los servicios antes de revertir la migración.');
        }
        Schema::table('servicios', function (Blueprint $table): void {
            $table->uuid('categoria_id')->nullable(false)->change();
            $table->uuid('subcategoria_id')->nullable(false)->change();
            $table->uuid('unidad_id')->nullable(false)->change();
        });
    }
};
