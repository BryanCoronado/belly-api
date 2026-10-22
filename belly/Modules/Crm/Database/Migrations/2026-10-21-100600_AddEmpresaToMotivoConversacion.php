<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Cada empresa arma sus propios motivos para cerrar un chat. */
class AddEmpresaToMotivoConversacion extends Migration
{
    public function up()
    {
        $this->forge->addColumn('motivo_conversacion', [
            'id_empresa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => '#64748b',
                'after'      => 'nombre',
            ],
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'after'      => 'color',
            ],
        ]);

        $this->db->query('ALTER TABLE motivo_conversacion ADD INDEX idx_motivo_conversacion_empresa (id_empresa, deleted_at, orden)');
        $this->db->query('ALTER TABLE motivo_conversacion ADD CONSTRAINT fk_motivo_conversacion_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE motivo_conversacion DROP FOREIGN KEY fk_motivo_conversacion_empresa');
        $this->db->query('ALTER TABLE motivo_conversacion DROP INDEX idx_motivo_conversacion_empresa');
        $this->forge->dropColumn('motivo_conversacion', ['id_empresa', 'color', 'orden']);
    }
}
