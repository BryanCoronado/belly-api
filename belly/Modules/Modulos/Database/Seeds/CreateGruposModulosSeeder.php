<?php

namespace Belly\Modulos\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateGruposModulosSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Modulos\\Database\\Seeds\\CreateGruposModulosSeeder
     */

    public function run()
    {
        $data = [
            [
                'id' => 1,
                'grupo' => 'Administradores',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'grupo' => 'Leads',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'grupo' => 'Marketing',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 4,
                'grupo' => 'Integraciones',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 5,
                'grupo' => 'Efectividad',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 6,
                'grupo' => 'Web',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('modulo_grupo')->insertBatch($data);
    }
}
