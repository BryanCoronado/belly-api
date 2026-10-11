<?php

namespace Belly\Configuracion\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Belly\Configuracion\Database\Seeds\CreateAppSeeder;
use Belly\Configuracion\Database\Seeds\CreateUsuarioAppSeeder;

class CreatePortafolioSeeder extends Seeder
{


    public function run()
    {
        $existe = $this->db->table('empresa')->where('id', 1)->countAllResults();
        if ($existe === 0) {
            $this->db->table('empresa')->insert([
                'id' => 1,
                'nombre' => 'Empresa principal',
                'slug' => 'principal',
                'plan' => 'base',
                'estado' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $data = [
            [
                'id' => 1,
                'id_empresa' => 1,
                'nombre' => 'p uno',
                'business_id' => '11111111',
                'estado' => 1,
            ],
            [
                'id' => 2,
                'id_empresa' => 1,
                'nombre' => 'p dos',
                'business_id' => '22222222',
                'estado' => 1,
            ],
        ];

        $this->db->table('portafolio')->insertBatch($data);

        $this->call(CreateAppSeeder::class);
        $this->call(CreateUsuarioAppSeeder::class);
    }
}
