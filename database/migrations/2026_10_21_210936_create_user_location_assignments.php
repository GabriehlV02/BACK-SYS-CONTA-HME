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
        Schema::create('sucursal_usuario', function (Blueprint $table): void {
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales_inventario')->restrictOnDelete();
            $table->primary(['usuario_id', 'sucursal_id']);
        });
        Schema::create('almacen_usuario', function (Blueprint $table): void {
            $table->foreignUuid('usuario_id')->constrained('usuarios_sistema')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes_inventario')->restrictOnDelete();
            $table->primary(['usuario_id', 'almacen_id']);
        });
        foreach (DB::table('usuarios_sistema')->select('id', 'sucursal', 'almacenes')->cursor() as $usuario) {
            $sucursales = DB::table('sucursales_inventario')->where('nombre', $usuario->sucursal)->pluck('id');
            if ($sucursales->count() === 1) {
                DB::table('sucursal_usuario')->insert(['usuario_id' => $usuario->id, 'sucursal_id' => $sucursales->first()]);
                foreach (json_decode($usuario->almacenes ?? '[]', true) ?: [] as $nombre) {
                    $almacenes = DB::table('almacenes_inventario')->where('sucursal_id', $sucursales->first())
                        ->where(fn ($q) => $q->where('nombre', $nombre)->orWhere('codigo', $nombre))->pluck('id');
                    if ($almacenes->count() === 1) {
                        DB::table('almacen_usuario')->insertOrIgnore(['usuario_id' => $usuario->id, 'almacen_id' => $almacenes->first()]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('almacen_usuario');
        Schema::drop('sucursal_usuario');
    }
};
