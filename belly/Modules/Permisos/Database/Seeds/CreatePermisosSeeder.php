<?php

namespace Belly\Permisos\Database\Seeds;

use Belly\Database\Catalogo\CatalogoAcceso;
use Belly\Database\Seeds\AseguraCatalogo;
use CodeIgniter\Database\Seeder;

class CreatePermisosSeeder extends Seeder
{
    use AseguraCatalogo;

    /*
     * php spark db:seed \\Belly\\Permisos\\Database\\Seeds\\CreatePermisosSeeder
     */

    public function run()
    {
        $ahora = date('Y-m-d H:i:s');
        $data = array_map(static fn (array $fila) => $fila + ['created_at' => $ahora], CatalogoAcceso::permisos());

        // Primero los padres: la FK id_permiso_padre exige que existan.
        usort($data, static fn (array $a, array $b) => ($a['id_permiso_padre'] === null ? 0 : 1) <=> ($b['id_permiso_padre'] === null ? 0 : 1));

        $this->asegurarPorId('permiso', $data);
    }
}
