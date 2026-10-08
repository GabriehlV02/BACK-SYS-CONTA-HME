<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tipos_usuarios', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->string('codigo', 30)->unique(); $t->string('nombre', 80); $t->text('descripcion')->nullable(); $t->boolean('activo')->default(true); $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->string('codigo', 60)->unique(); $t->string('nombre', 100); $t->text('descripcion')->nullable(); $t->boolean('activo')->default(true); $t->timestamps();
        });
        Schema::create('permisos', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->string('codigo', 100)->unique(); $t->string('nombre', 120); $t->string('modulo', 80); $t->text('descripcion')->nullable(); $t->boolean('activo')->default(true); $t->timestamps();
        });
        Schema::create('permiso_rol', function (Blueprint $t): void {
            $t->uuid('rol_id'); $t->uuid('permiso_id'); $t->primary(['rol_id','permiso_id']); $t->foreign('rol_id')->references('id')->on('roles')->cascadeOnDelete(); $t->foreign('permiso_id')->references('id')->on('permisos')->cascadeOnDelete();
        });
        Schema::create('rol_usuario', function (Blueprint $t): void {
            $t->uuid('usuario_id'); $t->uuid('rol_id'); $t->primary(['usuario_id','rol_id']); $t->foreign('usuario_id')->references('id')->on('usuarios_sistema')->cascadeOnDelete(); $t->foreign('rol_id')->references('id')->on('roles')->cascadeOnDelete();
        });
        Schema::table('usuarios_sistema', function (Blueprint $t): void { $t->uuid('tipo_usuario_id')->nullable()->after('tipo_usuario'); $t->foreign('tipo_usuario_id')->references('id')->on('tipos_usuarios')->nullOnDelete(); });
    }
    public function down(): void { Schema::table('usuarios_sistema', function (Blueprint $t): void { $t->dropForeign(['tipo_usuario_id']); $t->dropColumn('tipo_usuario_id'); }); Schema::dropIfExists('rol_usuario'); Schema::dropIfExists('permiso_rol'); Schema::dropIfExists('permisos'); Schema::dropIfExists('roles'); Schema::dropIfExists('tipos_usuarios'); }
};
