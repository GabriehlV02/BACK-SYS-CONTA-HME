<?php

namespace App\Flows\Usuarios\Models;

use App\Models\AlmacenInventario;
use App\Models\SucursalInventario;
use Database\Factories\UsuarioSistemaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class UsuarioSistema extends Authenticatable
{
    /** @use HasFactory<UsuarioSistemaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'usuarios_sistema';

    protected $hidden = ['password'];

    protected $fillable = ['tipo_usuario', 'paciente_id', 'nombres', 'apellidos', 'ci', 'correo', 'usuario', 'password', 'rol', 'estado', 'sucursal', 'cajas', 'almacenes'];

    protected $rememberTokenName = null;

    public function sucursalesAsignadas(): BelongsToMany
    {
        return $this->belongsToMany(SucursalInventario::class, 'sucursal_usuario', 'usuario_id', 'sucursal_id');
    }

    public function almacenesAsignados(): BelongsToMany
    {
        return $this->belongsToMany(AlmacenInventario::class, 'almacen_usuario', 'usuario_id', 'almacen_id');
    }

    protected static function newFactory(): UsuarioSistemaFactory
    {
        return UsuarioSistemaFactory::new();
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'cajas' => 'array', 'almacenes' => 'array'];
    }

    public function puedeAsignarRol(string $rolId): bool
    {
        if ($this->rolesContables()->where('roles.codigo', 'ADMINISTRADOR')->exists()) {
            return true;
        }

        $propios = DB::table('permiso_rol')->whereIn('rol_id', $this->rolesContables()->select('roles.id'))->select('permiso_id');

        if (DB::table('roles')->where('id', $rolId)->where('codigo', 'ADMINISTRADOR')->exists()) {
            return false;
        }

        return ! DB::table('permiso_rol')->where('rol_id', $rolId)->whereNotIn('permiso_id', $propios)->exists();
    }

    public function rolesContables(): Builder
    {
        return DB::table('roles')
            ->join('rol_usuario', 'rol_usuario.rol_id', '=', 'roles.id')
            ->join('rol_sistema', 'rol_sistema.rol_id', '=', 'roles.id')
            ->join('sistemas', 'sistemas.id', '=', 'rol_sistema.sistema_id')
            ->where('rol_usuario.usuario_id', $this->getKey())
            ->where('roles.activo', true)
            ->where('sistemas.codigo', 'CONTABLE')
            ->where('sistemas.activo', true);
    }

    public function tieneAccesoContable(): bool
    {
        return $this->estado === 'ACTIVO'
            && $this->tipo_usuario === 'EMPRESA'
            && $this->rolesContables()->exists();
    }

    /** @return list<string> */
    public function permisosContables(): array
    {
        return DB::table('permisos')
            ->join('permiso_rol', 'permiso_rol.permiso_id', '=', 'permisos.id')
            ->join('sistemas', 'sistemas.id', '=', 'permisos.sistema_id')
            ->whereIn('permiso_rol.rol_id', $this->rolesContables()->select('roles.id'))
            ->where('permisos.activo', true)
            ->where('sistemas.codigo', 'CONTABLE')
            ->where('sistemas.activo', true)
            ->distinct()->orderBy('permisos.codigo')->pluck('permisos.codigo')->all();
    }
}
