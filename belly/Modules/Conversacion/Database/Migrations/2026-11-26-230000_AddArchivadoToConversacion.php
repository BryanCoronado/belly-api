<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddArchivadoToConversacion extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('archivado', 'conversacion')) {
            return;
        }

        $this->forge->addColumn('conversacion', [
            'archivado' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'null' => false,
                'default' => 0,
                'after' => 'id_estado',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('archivado', 'conversacion')) {
            $this->forge->dropColumn('conversacion', 'archivado');
        }
    }
}
