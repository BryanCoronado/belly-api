<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateRolesSeeder extends Seeder {
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\CreateRolesSeeder
     */

    public function run() {
        $data = [
            [
                'id' => 1,
                'id_empresa' => 1,
                'es_admin' => 1,
                'rol' => 'Programador',
                'alias' => 'rol_dev',
                'descripcion' => 'Rol para los Programadores del sistema.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'id_empresa' => 1,
                'es_admin' => 1,
                'rol' => 'Administrador',
                'alias' => 'rol_admin',
                'descripcion' => 'Rol para los Administradores del sistema.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'id_empresa' => 1,
                'es_admin' => 0,
                'rol' => 'Jefe de Sistemas',
                'alias' => 'rol_jefatura_sistemas',
                'descripcion' => 'Rol para jefatura de sistemas.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 4,
                'id_empresa' => 1,
                'es_admin' => 0,
                'rol' => 'Jefe de Marketing',
                'alias' => 'rol_jefatura_marketing',
                'descripcion' => 'Rol para jefatura de marketing.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 5,
                'id_empresa' => 1,
                'es_admin' => 0,
                'rol' => 'Personal de Contac Center',
                'alias' => 'rol_personal_contac_center',
                'descripcion' => 'Rol para personal de contact center',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 6,
                'id_empresa' => 1,
                'es_admin' => 0,
                'rol' => 'Jefe de Contac Center',
                'alias' => 'rol_jefatura_contac_center',
                'descripcion' => 'Rol de jefatura de contac center',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 7,
                'id_empresa' => 1,
                'es_admin' => 0,
                'rol' => 'Personal de Marketing',
                'alias' => 'rol_personal_marketing',
                'descripcion' => 'Personal de marketing',
                'created_at' => date('Y-m-d H:i:s'),
            ],

        ];

        $this->db->table('grupo_usuario_rol')->insertBatch($data);
    }
}
