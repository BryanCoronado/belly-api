<?php

namespace Belly\Mensajes\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateEstadoMensajeSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'inicial',
                'descripcion' => 'mensaje inicial',
            ],
            [
                'id' => 2,
                'nombre' => 'entregado',
                'descripcion' => 'mensaje entregado',
            ],
            [
                'id' => 3,
                'nombre' => 'recibido',
                'descripcion' => 'mensaje recibido',
            ],
            [
                'id' => 4,
                'nombre' => 'leido',
                'descripcion' => 'mensaje leido',
            ],
            [
                'id' => 5,
                'nombre' => 'error',
                'descripcion' => 'mensaje con error',
            ],
           
        ];

        $this->db->table('estado_mensaje')->insertBatch($data);

    }
}
