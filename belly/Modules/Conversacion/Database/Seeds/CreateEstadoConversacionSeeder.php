<?php

namespace Belly\Conversacion\Database\Seeds;

use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreateEstadoConversacionSeeder extends Seeder
{
    use AseguraCatalogo;

    public function run()
    {
        $this->asegurarPorId('estado_conversacion', [
            ['id' => 1, 'nombre' => 'en_atencion', 'descripcion' => 'conversacion en atencion'],
            ['id' => 2, 'nombre' => 'finalizado', 'descripcion' => 'conversacion finalizada'],
            ['id' => 3, 'nombre' => 'sin_abrir', 'descripcion' => 'conversacion sin abrir'],
        ]);
    }
}
