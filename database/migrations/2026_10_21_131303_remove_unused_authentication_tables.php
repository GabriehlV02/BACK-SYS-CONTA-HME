<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLAS = ['permiso_usuario_sistema', 'rol_usuario_sistema', 'usuario_sistema', 'password_reset_tokens', 'users'];

    public function up(): void
    {
        $existentes = array_values(array_filter(self::TABLAS, fn (string $tabla): bool => Schema::hasTable($tabla)));
        if ($existentes !== [] && DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE '.implode(', ', $existentes).' IN ACCESS EXCLUSIVE MODE');
        }
        foreach ($existentes as $tabla) {
            if (DB::table($tabla)->exists()) {
                throw new RuntimeException("No se elimina {$tabla}: contiene datos que deben revisarse antes de limpiar el esquema.");
            }
        }
        foreach ($existentes as $tabla) {
            Schema::drop($tabla);
        }
    }

    public function down(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
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
};
