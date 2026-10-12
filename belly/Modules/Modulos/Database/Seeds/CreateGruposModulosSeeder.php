<?php

namespace Belly\Modulos\Database\Seeds;

use Belly\Database\Catalogo\CatalogoAcceso;
use CodeIgniter\Database\Seeder;

class CreateGruposModulosSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Modulos\\Database\\Seeds\\CreateGruposModulosSeeder
     */

    public function run()
    {
        $ahora = date('Y-m-d H:i:s');
        $data = array_map(
            static fn (array $g) => ['id' => $g['id'], 'grupo' => $g['grupo'], 'created_at' => $ahora],
            CatalogoAcceso::grupos(),
        );

        $this->db->table('modulo_grupo')->insertBatch($data);
    }
}
