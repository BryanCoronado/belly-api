<?php

namespace Belly\Conversacion\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateEstadoConversacionSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'en_atencion',
                'descripcion' => 'conversacion en atencion',
            ],
            [
                'id' => 2,
                'nombre' => 'finalizado',
                'descripcion' => 'conversacion finalizada',
            ],
            [
                'id' => 3,
                'nombre' => 'sin_abrir',
                'descripcion' => 'conversacion sin abrir',
            ],
           
        ];

        $this->db->table('estado_conversacion')->insertBatch($data);

    }
}
