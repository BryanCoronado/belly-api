<?php

namespace Belly\Mensajes\Database\Seeds;

use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreateEstadoPlantillaMetaSeeder extends Seeder
{
    use AseguraCatalogo;

    public function run()
    {
        $this->asegurarPorId('estado_plantilla_meta', [
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
        ]);
    }
}
