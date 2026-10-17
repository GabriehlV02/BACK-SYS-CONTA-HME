<?php

namespace App\Flows\Usuarios\Http;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UsuarioSistema */
class SesionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario' => $this->usuario,
            'nombre' => trim($this->nombres.' '.$this->apellidos),
            'rol' => $this->rolesContables()->orderBy('roles.nombre')->pluck('roles.nombre')->implode(', '),
            'permisos' => $this->permisosContables(),
        ];
    }
}
