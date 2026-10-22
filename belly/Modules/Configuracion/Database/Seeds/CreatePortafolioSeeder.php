<?php

namespace Belly\Configuracion\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreatePortafolioSeeder extends Seeder
{
    public function run()
    {
        $existe = $this->db->table('empresa')->where('id', 1)->countAllResults();
        if ($existe === 0) {
            $this->db->table('empresa')->insert([
                'id' => 1,
                'nombre' => 'Empresa demo',
                'slug' => 'demo',
                'plan' => 'base',
                'estado' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->table('portafolio')->insert([
            'id' => 1,
            'id_empresa' => 1,
            'nombre' => 'Principal',
            'business_id' => '11111111',
            'estado' => 1,
        ]);

        $this->call(CreateAppSeeder::class);
        $this->call(CreateUsuarioAppSeeder::class);
    }
}
