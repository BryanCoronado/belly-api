<?php

namespace Belly\Permisos\Database\Seeds;

use Belly\Database\Catalogo\CatalogoAcceso;
use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreatePermisosGruposSeeder extends Seeder
{
    use AseguraCatalogo;

    /*
     * php spark db:seed \\Belly\\Permisos\\Database\\Seeds\\CreatePermisosGruposSeeder
     */

    public function run()
    {
        $ahora = date('Y-m-d H:i:s');
        $data = array_map(static fn (array $g) => $g + ['created_at' => $ahora], CatalogoAcceso::grupos());

        $this->asegurarPorId('grupo_permiso', $data);
    }
}
