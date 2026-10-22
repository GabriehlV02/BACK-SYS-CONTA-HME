<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (config('queue.default') === 'database') {
            throw new RuntimeException('Configura QUEUE_CONNECTION=sync antes de retirar las tablas de colas sin uso.');
        }
        $tablas = ['failed_jobs', 'job_batches', 'jobs'];
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('LOCK TABLE '.implode(', ', $tablas).' IN ACCESS EXCLUSIVE MODE');
        }
        foreach ($tablas as $tabla) {
            if (DB::table($tabla)->exists()) {
                throw new RuntimeException("No se elimina {$tabla}: existen trabajos que deben revisarse.");
            }
        }
        foreach ($tablas as $tabla) {
            Schema::drop($tabla);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $migracion = require database_path('migrations/0001_01_01_000002_create_jobs_table.php');
        $migracion->up();
    }
};
