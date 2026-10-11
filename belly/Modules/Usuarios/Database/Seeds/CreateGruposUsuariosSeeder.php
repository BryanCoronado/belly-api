<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateGruposUsuariosSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateGruposUsuariosSeeder
     */

    public function run()
    {
        $data = [
            [
                'id' => 1,
                'grupo' => 'Sistemas',
                'descripcion' => 'Grupo de Usuarios',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'grupo' => 'Contact Center',
                'descripcion' => 'Grupo de usuarios que se encargan de la  Prospección de nuevos clientes',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'grupo' => 'Marketing',
                'descripcion' => 'Grupo de usuarios que se encargan de la Marketing',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('grupo_usuario')->insertBatch($data);
    }
}
