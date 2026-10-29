<?php

namespace Belly\Usuarios\Database\Seeds;

use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class TiposDocumentosSeeder extends Seeder
{
    use AseguraCatalogo;

    public function run()
    {
        $this->asegurarPorId('tipo_documento', [
            [
                'id' => 1,
                'tipo' => 'DNI',
                'longitud' => 8,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
