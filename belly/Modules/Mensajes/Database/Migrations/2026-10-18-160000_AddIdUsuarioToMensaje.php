<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Quién del equipo envió el mensaje (admin, asesor, etc.). */
class AddIdUsuarioToMensaje extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('id_usuario', 'mensaje')) {
            return;
        }

        $this->forge->addColumn('mensaje', [
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'contesta',
            ],
        ]);
        $this->db->query('ALTER TABLE mensaje ADD INDEX idx_mensaje_id_usuario (id_usuario)');
    }

    public function down()
    {
        if (! $this->db->fieldExists('id_usuario', 'mensaje')) {
            return;
        }
        $this->forge->dropColumn('mensaje', 'id_usuario');
    }
}
