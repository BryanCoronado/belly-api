<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCrmToCliente extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('id_etapa', 'cliente')) {
            return;
        }

        $this->forge->addColumn('cliente', [
            'id_etapa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'wa_parent_user_id',
            ],
            'etapa_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'id_etapa',
            ],
            'id_tipo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'etapa_at',
            ],
            'notas' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'id_tipo',
            ],
        ]);

        $this->db->query('CREATE INDEX idx_cliente_empresa_etapa ON cliente (id_empresa, id_etapa)');
        $this->db->query('CREATE INDEX idx_cliente_empresa_tipo ON cliente (id_empresa, id_tipo)');
    }

    public function down()
    {
        if (! $this->db->fieldExists('id_etapa', 'cliente')) {
            return;
        }
        $this->db->query('DROP INDEX idx_cliente_empresa_etapa ON cliente');
        $this->db->query('DROP INDEX idx_cliente_empresa_tipo ON cliente');
        $this->forge->dropColumn('cliente', ['id_etapa', 'etapa_at', 'id_tipo', 'notas']);
    }
}
