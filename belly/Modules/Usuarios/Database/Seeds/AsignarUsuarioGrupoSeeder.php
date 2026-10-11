<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AsignarUsuarioGrupoSeeder extends Seeder {
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\AsignarUsuarioGrupoSeeder
     */

    public function run() {
        $data = [

            // PERSONAL SISTEMAS PROGRAMADOR
            [
                'id' => 1,
                'id_grupo_usuario' => 1,
                'id_usuario' => 1,
                'id_grupo_usuario_rol' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 19,
                'id_grupo_usuario' => 1,
                'id_usuario' => 2,
                'id_grupo_usuario_rol' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],  
        ];

        $this->db->table('grupo_usuario_usuario')->insertBatch($data);
    }
}
