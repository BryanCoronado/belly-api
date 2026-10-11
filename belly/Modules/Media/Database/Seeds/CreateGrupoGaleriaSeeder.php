<?php

namespace Belly\Media\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateGrupoGaleriaSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'frio',
                'descripcion' => 'grupo de multimedia para conversacion frio',
            ],
            [
                'id' => 2,
                'nombre' => 'tibio',
                'descripcion' => 'grupo de multimedia para conversacion tibio',
            ],
            [
                'id' => 3,
                'nombre' => 'caliente',
                'descripcion' => 'grupo de multimedia para conversacion caliente',
            ],

        ];

        $this->db->table('grupo_galeria')->insertBatch($data);

    }
}
