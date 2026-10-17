<?php

namespace App\Console\Commands;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('usuarios:crear-admin {usuario=admin} {--nombre=Administrador local} {--password= : Contraseña inicial; omite para solicitarla de forma oculta}')]
#[Description('Crea un administrador contable sin modificar usuarios existentes')]
class CrearAdministrador extends Command
{
    public function handle(): int
    {
        $usuario = trim((string) $this->argument('usuario'));
        $nombre = trim((string) $this->option('nombre'));
        $validator = Validator::make(
            ['usuario' => $usuario, 'nombre' => $nombre],
            ['usuario' => ['required', 'string', 'max:60'], 'nombre' => ['required', 'string', 'max:100']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (UsuarioSistema::where('usuario', $usuario)->exists()) {
            $this->error('Ese usuario ya existe. No se modificó su contraseña ni sus permisos.');

            return self::FAILURE;
        }

        $rolId = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->where('activo', true)->value('id');
        if (! $rolId) {
            $this->error('Primero ejecuta php artisan db:seed para cargar los roles iniciales.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('Contraseña (mínimo 5 caracteres; deja vacío para generar una)');
        }
        $password = $password ?: Str::password(20);

        if (mb_strlen($password) < 5 || mb_strlen($password) > 255) {
            $this->error('La contraseña debe tener entre 5 y 255 caracteres.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($usuario, $nombre, $password, $rolId): void {
            $administrador = UsuarioSistema::create([
                'tipo_usuario' => 'EMPRESA',
                'nombres' => $nombre,
                'usuario' => $usuario,
                'password' => $password,
                'rol' => 'ADMINISTRADOR',
                'estado' => 'ACTIVO',
            ]);
            DB::table('rol_usuario')->insert(['usuario_id' => $administrador->id, 'rol_id' => $rolId]);
        });

        $this->info('Administrador creado.');
        $this->line('Usuario: '.$usuario);
        $this->line('Contraseña: '.$password);

        return self::SUCCESS;
    }
}
