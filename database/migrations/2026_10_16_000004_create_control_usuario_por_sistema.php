<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_sistema', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('sistema_id');
            $table->boolean('activo')->default(true);
            $table->text('motivo_desactivacion')->nullable();
            $table->timestamp('desactivado_en')->nullable();
            $table->timestamps();

            $table->unique(['usuario_id', 'sistema_id']);
            $table->foreign('usuario_id')->references('id')->on('usuarios_sistema')->cascadeOnDelete();
            $table->foreign('sistema_id')->references('id')->on('sistemas')->cascadeOnDelete();
        });

        Schema::create('rol_usuario_sistema', function (Blueprint $table): void {
            $table->uuid('usuario_sistema_id');
            $table->uuid('rol_id');
            $table->timestamps();

            $table->primary(['usuario_sistema_id', 'rol_id']);
            $table->foreign('usuario_sistema_id')->references('id')->on('usuario_sistema')->cascadeOnDelete();
            $table->foreign('rol_id')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::create('permiso_usuario_sistema', function (Blueprint $table): void {
            $table->uuid('usuario_sistema_id');
            $table->uuid('permiso_id');
            $table->string('efecto', 10)->default('DENEGAR');
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->primary(['usuario_sistema_id', 'permiso_id']);
            $table->foreign('usuario_sistema_id')->references('id')->on('usuario_sistema')->cascadeOnDelete();
            $table->foreign('permiso_id')->references('id')->on('permisos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso_usuario_sistema');
        Schema::dropIfExists('rol_usuario_sistema');
        Schema::dropIfExists('usuario_sistema');
    }
};
