<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ConcederAccesoSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\ConcederAccesoSeeder
     */

    public function run()
    {
        $permisos = [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
            21, 22, 23, 24, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44,
        ];

        $data = [];
        foreach ($permisos as $idPermiso) {
            $data[] = [
                'id_grupo_usuario' => 1,
                'id_permiso' => $idPermiso,
                'acc_crear' => 1,
                'acc_lectura' => 1,
                'acc_actualizar' => 1,
                'acc_eliminar' => 1,
                'activo' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('grupo_usuario_permiso')->insertBatch($data);
    }
}
