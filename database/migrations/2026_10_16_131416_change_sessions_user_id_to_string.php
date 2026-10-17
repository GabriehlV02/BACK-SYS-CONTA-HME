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
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id', 36)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // PostgreSQL rechazará el rollback si aún existen sesiones con UUID.
        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->using('user_id::bigint')->change();
        });
    }
};
