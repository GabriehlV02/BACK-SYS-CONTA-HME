<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE usuarios_sistema, tipos_usuarios IN ACCESS EXCLUSIVE MODE');
        }
        $inconsistentes = DB::table('usuarios_sistema as u')->join('tipos_usuarios as t', 't.id', '=', 'u.tipo_usuario_id')
            ->whereColumn('u.tipo_usuario', '!=', 't.codigo')->exists();
        if ($inconsistentes) {
            throw new RuntimeException('Hay tipos de usuario inconsistentes; corrígelos antes de retirar el catálogo duplicado.');
        }
        Schema::table('usuarios_sistema', function (Blueprint $table): void {
            $table->dropForeign(['tipo_usuario_id']);
            $table->dropColumn('tipo_usuario_id');
        });
        Schema::drop('tipos_usuarios');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('tipos_usuarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::table('usuarios_sistema', function (Blueprint $table): void {
            $table->foreignUuid('tipo_usuario_id')->nullable()->constrained('tipos_usuarios')->nullOnDelete();
        });
        foreach (['EMPRESA' => 'Trabajador de la empresa', 'PACIENTE' => 'Paciente con acceso al portal'] as $codigo => $nombre) {
            DB::table('tipos_usuarios')->insert(['id' => (string) Str::uuid(), 'codigo' => $codigo, 'nombre' => $nombre, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
