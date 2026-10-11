<?php

namespace Belly\Configuracion\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateUsuarioAppSeeder extends Seeder
{


    public function run()
    {
        $data = [
            // app uno
            [
                'id_usuario' => 1,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 2,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 3,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 4,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 5,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 6,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 7,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 8,
                'id_app' => 1, 
            ],
            [
                'id_usuario' => 9,
                'id_app' => 1, 
            ],
           
        ];

        $this->db->table('usuario_app')->insertBatch($data);

    }
}
