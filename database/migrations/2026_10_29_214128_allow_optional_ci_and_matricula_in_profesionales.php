<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profesionales', function (Blueprint $table): void {
            $table->string('ci', 25)->nullable()->change();
            $table->string('matricula', 60)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('profesionales')->whereNull('ci')->orWhereNull('matricula')->exists()) {
            throw new RuntimeException('Completa los CI y matrículas antes de revertir esta migración.');
        }
        Schema::table('profesionales', function (Blueprint $table): void {
            $table->string('ci', 25)->nullable(false)->change();
            $table->string('matricula', 60)->nullable(false)->change();
        });
    }
};
