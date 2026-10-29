<?php

namespace Belly\Configuracion\Database\Seeds;

use Belly\Database\Seeds\SoloDesarrollo;
use CodeIgniter\Database\Seeder;

/** Solo desarrollo: portafolio y número de WhatsApp de prueba de la empresa 1. */
class CreatePortafolioSeeder extends Seeder
{
    use SoloDesarrollo;

    public function run()
    {
        $this->exigirDesarrollo();

        if ($this->db->table('empresa')->where('id', 1)->countAllResults() === 0) {
            $fila = [
                'id' => 1,
                'nombre' => 'Empresa demo',
                'slug' => 'demo',
                'plan' => 'base',
                'estado' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->db->fieldExists('correo', 'empresa')) {
                $fila['correo'] = 'contacto@demo.belly.test';
            }
            $this->db->table('empresa')->insert($fila);
        }

        if ($this->db->table('portafolio')->where('id_empresa', 1)->where('deleted_at', null)->countAllResults() === 0) {
            $this->db->table('portafolio')->insert([
                'id_empresa' => 1,
                'nombre' => 'whatsapp',
                'business_id' => 0,
                'estado' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->call(CreateAppSeeder::class);
        $this->call(CreateUsuarioAppSeeder::class);
    }
}
