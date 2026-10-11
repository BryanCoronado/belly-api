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
                'access_token' => 'EAAmqYb6ynykBRS7kBZBxiES8qy1RAR2RvQ2eg0VGF78NSAKXgvp3oTvQPTxDgZAGxSyXI0wZBCGubB5ntGYJP3zpQdhYvCI3zrz2DCx1T0G1kLESGcXAgxZAb1LdCKq3oQak5NIXct7nyCs2Nmgien3LQgiLUzlr2dxiirHZBmZB3lsLWjDBKAkK9oleuxWAZDZD',
                'modo' => 'compartido',
                'max_usuarios' => 30,
                'tipo' => 'NORMAL'
            ],
            
        ];

        $this->db->table('app')->insertBatch($data);

    }
}
