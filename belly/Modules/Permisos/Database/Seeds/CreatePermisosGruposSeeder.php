<?php

namespace Belly\Permisos\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreatePermisosGruposSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Permisos\\Database\\Seeds\\CreatePermisosGruposSeeder
     */

    public function run()
    {
        $data = [
            [
                'id' => 1,
                'grupo' => 'Sistemas',
                'descripcion' => 'Grupo de Permisos para el area de Sistemas.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'grupo' => 'Leads',
                'descripcion' => 'Grupo de Permisos para el area de Leads.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'grupo' => 'Marketing',
                'descripcion' => 'Grupo de Permisos para el area de Marketing.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 4,
                'grupo' => 'Chat permisos',
                'descripcion' => 'permisos especiales para mod. chat',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 5,
                'grupo' => 'Integracion',
                'descripcion' => 'permisos especiales para mod. integracion',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 6,
                'grupo' => 'Efectividad',
                'descripcion' => 'permisos especiales para mod. integracion',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'id' => 7,
                'grupo' => 'Web',
                'descripcion' => 'Grupo de Permisos para el area de Pagina Web.',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('grupo_permiso')->insertBatch($data);
    }
}
