<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddResponsableToCliente extends Migration
{
    public function up()
    {
        $this->forge->addColumn('cliente', [
            'id_responsable' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'notas',
            ],
            'origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'comment'    => 'NULL = llegó por WhatsApp; manual; importado',
                'after'      => 'id_responsable',
            ],
        ]);

        $this->db->query('CREATE INDEX idx_cliente_empresa_responsable ON cliente (id_empresa, id_responsable)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX idx_cliente_empresa_responsable ON cliente');
        $this->forge->dropColumn('cliente', ['id_responsable', 'origen']);
    }
}
