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
        Schema::table('farmacia_inventarios', function (Blueprint $table): void {
            $table->foreignId('sucursal_id')->nullable()->change();
            $table->foreignId('almacen_id')->nullable()->change();
            $table->string('estado', 24)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farmacia_inventarios', function (Blueprint $table): void {
            $table->foreignId('sucursal_id')->nullable(false)->change();
            $table->foreignId('almacen_id')->nullable(false)->change();
            $table->string('estado', 16)->change();
        });
    }
};
