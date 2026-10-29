<?php

namespace Belly\Modulos\Database\Seeds;

use Belly\Database\Catalogo\CatalogoAcceso;
use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreateModulosSeeder extends Seeder
{
    use AseguraCatalogo;

    /*
     * php spark db:seed \\Belly\\Modulos\\Database\\Seeds\\CreateModulosSeeder
     */

    public function run()
    {
        $ahora = date('Y-m-d H:i:s');
        $data = array_map(static fn (array $m) => $m + ['created_at' => $ahora], CatalogoAcceso::modulos());

        $this->asegurarPorId('modulo', $data);
    }
}
