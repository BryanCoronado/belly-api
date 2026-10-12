<?php

namespace Belly\Usuarios\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ConcederAccesoSeeder extends Seeder
{
    /*
     * php spark db:seed \\Belly\\Usuarios\\Database\\Seeds\\ConcederAccesoSeeder
     *
     * Los permisos se otorgan por rol: los roles administradores de la empresa 1 reciben todo el catálogo.
     */

    private const ID_EMPRESA = 1;
    private const ID_GRUPO_ADMIN = 1;

    public function run()
    {
        $permisos = $this->db->table('permiso')
            ->select('id')
            ->where('activo', 1)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $roles = $this->db->table('grupo_usuario_rol')
            ->select('id')
            ->where('id_empresa', self::ID_EMPRESA)
            ->where('es_admin', 1)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $data = [];
        foreach ($roles as $rol) {
            foreach ($permisos as $permiso) {
                $data[] = [
                    'id_grupo_usuario' => self::ID_GRUPO_ADMIN,
                    'id_grupo_usuario_rol' => (int) $rol['id'],
                    'id_permiso' => (int) $permiso['id'],
                    'acc_crear' => 1,
                    'acc_lectura' => 1,
                    'acc_actualizar' => 1,
                    'acc_eliminar' => 1,
                    'activo' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        if ($data !== []) {
            $this->db->table('grupo_usuario_permiso_rol')->insertBatch($data);
        }
    }
}
