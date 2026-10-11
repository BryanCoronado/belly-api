<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TiposDocumentosSeeder extends Seeder {

    public function run() {
        $data = [
            
            [
                'id' => 1,
                'tipo' => 'DNI',
                'longitud' => 8,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            
        ];

        $this->db->table('tipo_documento')->insertBatch($data);

    }
}
