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
        Schema::create('profesionales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id')->nullable()->unique();
            $table->foreign('usuario_id')->references('id')->on('usuarios_sistema')->restrictOnDelete();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('ci', 25)->unique();
            $table->string('tipo', 30);
            $table->json('especialidades');
            $table->string('rol', 100);
            $table->string('matricula', 60)->unique();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profesionales');
    }
};
