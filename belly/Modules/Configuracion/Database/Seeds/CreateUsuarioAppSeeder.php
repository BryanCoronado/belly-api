<?php

namespace Belly\Configuracion\Database\Seeds;

use CodeIgniter\Database\Seeder;

/** Los administradores de la empresa 1 quedan asignados a su número de WhatsApp de prueba. */
class CreateUsuarioAppSeeder extends Seeder
{
    public function run()
    {
        $administradores = $this->db->table('usuario')
            ->select('id')
            ->where('id_empresa', 1)
            ->where('sudo', 1)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $filas = array_map(static fn (array $u): array => ['id_usuario' => (int) $u['id'], 'id_app' => 1], $administradores);
        if ($filas !== []) {
            $this->db->table('usuario_app')->insertBatch($filas);
        }
    }
}
