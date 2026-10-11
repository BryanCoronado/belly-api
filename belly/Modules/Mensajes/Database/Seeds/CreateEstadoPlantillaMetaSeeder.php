<?php

namespace Belly\Mensajes\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateEstadoPlantillaMetaSeeder extends Seeder
{


    public function run()
    {
        $data = [
            [
                'id' => 1,
                'nombre' => 'Activo',
                'alias' => 'activo',
                'descripcion' => 'indica que la plantilla está activa y puede ser utilizada',
            ],
            [
                'id' => 2,
                'nombre' => 'Desactivado',
                'alias' => 'desactivado',
                'descripcion' => 'indica que la plantilla está desactivada y no puede ser utilizada',
            ],
        ];

        $this->db->table('estado_plantilla_meta')->insertBatch($data);

    }
}
