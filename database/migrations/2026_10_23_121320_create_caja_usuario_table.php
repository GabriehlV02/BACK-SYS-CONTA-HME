<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_usuario', function (Blueprint $table): void {
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->primary(['usuario_id', 'caja_id']);
            $table->index('caja_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_usuario');
    }
};
