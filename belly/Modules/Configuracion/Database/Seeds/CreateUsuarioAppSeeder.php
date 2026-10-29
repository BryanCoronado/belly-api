<?php

namespace Belly\Configuracion\Database\Seeds;

use Belly\Database\Seeds\SoloDesarrollo;
use CodeIgniter\Database\Seeder;

/** Solo desarrollo: asigna los administradores de prueba al WhatsApp de prueba. */
class CreateUsuarioAppSeeder extends Seeder
{
    use SoloDesarrollo;

    public function run()
    {
        $this->exigirDesarrollo();

        $app = $this->db->table('app')
            ->select('id')
            ->where('id_empresa', 1)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get(1)
            ->getRowArray();
        if ($app === null) {
            return;
        }

        $idApp = (int) $app['id'];
        $administradores = $this->db->table('usuario')
            ->select('id')
            ->where('id_empresa', 1)
            ->where('sudo', 1)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($administradores as $usuario) {
            $idUsuario = (int) $usuario['id'];
            $existe = $this->db->table('usuario_app')
                ->where('id_usuario', $idUsuario)
                ->where('id_app', $idApp)
                ->countAllResults();
            if ($existe > 0) {
                continue;
            }
            $this->db->table('usuario_app')->insert([
                'id_usuario' => $idUsuario,
                'id_app' => $idApp,
            ]);
        }
    }
}
