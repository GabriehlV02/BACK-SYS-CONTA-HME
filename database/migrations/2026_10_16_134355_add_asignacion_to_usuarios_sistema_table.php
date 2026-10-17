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
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            $table->string('sucursal', 150)->nullable();
            $table->json('cajas')->nullable();
            $table->json('almacenes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios_sistema', function (Blueprint $table) {
            $table->dropColumn(['sucursal', 'cajas', 'almacenes']);
        });
    }
};
