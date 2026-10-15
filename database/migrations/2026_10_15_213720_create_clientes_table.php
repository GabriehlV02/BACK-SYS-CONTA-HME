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
        Schema::create('clientes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Referencia lógica: pacientes reside en la base de datos Clínica.
            $table->uuid('paciente_id')->nullable()->unique();
            $table->string('nit_ci', 30)->nullable()->unique();
            $table->string('nombre_razon_social', 180);
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 180)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
