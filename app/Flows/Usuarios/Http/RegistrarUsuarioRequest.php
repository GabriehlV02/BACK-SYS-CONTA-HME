<?php
namespace App\Flows\Usuarios\Http;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class RegistrarUsuarioRequest extends FormRequest { public function authorize(): bool{return true;} public function rules(): array{return ['tipo_usuario'=>['required',Rule::in(['EMPRESA','PACIENTE'])],'paciente_id'=>['nullable','uuid','required_if:tipo_usuario,PACIENTE'],'nombres'=>['required','string','max:100'],'apellidos'=>['nullable','string','max:100'],'ci'=>['nullable','string','max:25'],'correo'=>['nullable','email','max:150'],'usuario'=>['required','string','max:60',Rule::unique('usuarios_sistema','usuario')],'password'=>['required','string','min:8'],'rol'=>['nullable','string','max:60'],'estado'=>['sometimes',Rule::in(['ACTIVO','INACTIVO'])]];} }
