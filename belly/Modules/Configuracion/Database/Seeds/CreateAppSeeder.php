<?php

namespace Belly\Configuracion\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateAppSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'id_empresa' => 1,
                'id_portafolio' => 1, 
                'nombre' => 'belly whatsapp',
                'telefono' => '+51908528231',
                'phone_number_id' => '1136836639502449',
                'whatsapp_business_account_id' => '978774918039409',
                'estado' => 1,
                'access_token' => \Belly\Services\Cifrado::cifrar(env('META_SEED_ACCESS_TOKEN') ?: null),
                'verificado_at' => env('META_SEED_ACCESS_TOKEN') ? date('Y-m-d H:i:s') : null,
                'modo' => 'compartido',
                'max_usuarios' => 30,
                'tipo' => 'NORMAL'
            ],
            
        ];

        $this->db->table('app')->insertBatch($data);

    }
}
