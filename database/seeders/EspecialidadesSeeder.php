<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EspecialidadesSeeder extends Seeder
{
    public function run(): void
    {
        $nombres = [
            'Medicina general', 'Medicina familiar', 'Medicina interna', 'Pediatría', 'Neonatología', 'Geriatría',
            'Cardiología', 'Cardiología pediátrica', 'Dermatología', 'Endocrinología', 'Endocrinología pediátrica',
            'Gastroenterología', 'Gastroenterología pediátrica', 'Hematología', 'Hematología pediátrica',
            'Infectología', 'Infectología pediátrica', 'Nefrología', 'Nefrología pediátrica', 'Neumología',
            'Neumología pediátrica', 'Neurología', 'Neurología pediátrica', 'Oncología médica', 'Oncología pediátrica',
            'Reumatología', 'Alergología e inmunología', 'Medicina de emergencias', 'Medicina crítica y terapia intensiva',
            'Terapia intensiva pediátrica', 'Medicina del dolor', 'Cuidados paliativos', 'Medicina física y rehabilitación',
            'Medicina deportiva', 'Medicina ocupacional', 'Medicina preventiva y salud pública', 'Medicina legal y forense',
            'Medicina tropical', 'Genética médica', 'Farmacología clínica', 'Nutriología clínica',
            'Psiquiatría', 'Psiquiatría infantil y adolescente', 'Psicología clínica', 'Ginecología y obstetricia',
            'Medicina materno fetal', 'Reproducción humana', 'Cirugía general', 'Cirugía pediátrica', 'Cirugía cardiovascular',
            'Cirugía torácica', 'Cirugía vascular', 'Cirugía plástica y reconstructiva', 'Cirugía oncológica',
            'Cirugía maxilofacial', 'Neurocirugía', 'Ortopedia y traumatología', 'Traumatología pediátrica',
            'Urología', 'Urología pediátrica', 'Oftalmología', 'Otorrinolaringología', 'Anestesiología',
            'Radiología e imagenología', 'Radiología intervencionista', 'Medicina nuclear', 'Radioterapia',
            'Patología', 'Anatomía patológica', 'Patología clínica', 'Microbiología clínica', 'Bioquímica clínica',
            'Laboratorio clínico', 'Transfusión y banco de sangre', 'Odontología general', 'Ortodoncia',
            'Endodoncia', 'Periodoncia', 'Odontopediatría', 'Enfermería general', 'Enfermería de emergencias',
            'Enfermería de terapia intensiva', 'Enfermería quirúrgica', 'Enfermería pediátrica',
            'Enfermería neonatal', 'Enfermería obstétrica', 'Instrumentación quirúrgica', 'Fisioterapia y kinesiología',
            'Nutrición y dietética', 'Fonoaudiología', 'Terapia ocupacional',
        ];
        $ahora = now();
        foreach ($nombres as $nombre) {
            DB::table('especialidades')->insertOrIgnore(['nombre' => $nombre, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }
}
