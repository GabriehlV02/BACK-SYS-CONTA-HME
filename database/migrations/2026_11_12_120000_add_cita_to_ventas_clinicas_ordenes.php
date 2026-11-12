<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('ventas_clinicas_ordenes', function (Blueprint $table): void { $table->unsignedBigInteger('cita_id')->nullable()->unique('ventas_clinicas_cita_unique'); }); }
    public function down(): void { Schema::table('ventas_clinicas_ordenes', function (Blueprint $table): void { $table->dropUnique('ventas_clinicas_cita_unique'); $table->dropColumn('cita_id'); }); }
};
