<?php

namespace App\Models;

use App\Flows\Usuarios\Models\UsuarioSistema;
use Database\Factories\ProfesionalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profesional extends Model
{
    /** @use HasFactory<ProfesionalFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'profesionales';

    public const TIPOS = ['Médico/a', 'Enfermero/a', 'Otro profesional'];

    protected $fillable = ['usuario_id', 'nombres', 'apellidos', 'ci', 'tipo', 'especialidades', 'rol', 'matricula', 'telefono', 'activo'];

    protected function casts(): array
    {
        return ['especialidades' => 'array', 'activo' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(UsuarioSistema::class, 'usuario_id');
    }
}
