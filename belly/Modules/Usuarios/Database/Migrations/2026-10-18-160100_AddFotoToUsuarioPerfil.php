<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFotoToUsuarioPerfil extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('foto_url', 'usuario_perfil')) {
            return;
        }

        $this->forge->addColumn('usuario_perfil', [
            'foto_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'celular_secundario',
            ],
            'foto_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'foto_url',
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('foto_url', 'usuario_perfil')) {
            return;
        }
        $this->forge->dropColumn('usuario_perfil', ['foto_url', 'foto_path']);
    }
}
