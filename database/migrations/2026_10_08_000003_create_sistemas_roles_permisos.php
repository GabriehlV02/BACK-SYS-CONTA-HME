<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sistemas', function (Blueprint $t): void { $t->uuid('id')->primary(); $t->string('codigo', 40)->unique(); $t->string('nombre', 100); $t->text('descripcion')->nullable(); $t->boolean('activo')->default(true); $t->timestamps(); });
        Schema::create('rol_sistema', function (Blueprint $t): void { $t->uuid('rol_id'); $t->uuid('sistema_id'); $t->primary(['rol_id','sistema_id']); $t->foreign('rol_id')->references('id')->on('roles')->cascadeOnDelete(); $t->foreign('sistema_id')->references('id')->on('sistemas')->cascadeOnDelete(); });
        Schema::table('permisos', function (Blueprint $t): void { $t->uuid('sistema_id')->nullable()->index(); $t->foreign('sistema_id')->references('id')->on('sistemas')->nullOnDelete(); });
    }
    public function down(): void { Schema::table('permisos', function (Blueprint $t): void { $t->dropForeign(['sistema_id']); $t->dropColumn('sistema_id'); }); Schema::dropIfExists('rol_sistema'); Schema::dropIfExists('sistemas'); }
};
