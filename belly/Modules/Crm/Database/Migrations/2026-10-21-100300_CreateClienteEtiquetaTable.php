<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Un cliente puede llevar varias etiquetas de su empresa. */
class CreateClienteEtiquetaTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('cliente_etiqueta')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_empresa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_etiqueta' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['id_cliente', 'id_etiqueta'], 'uk_cliente_etiqueta');
        $this->forge->addKey(['id_empresa', 'id_etiqueta'], false, false, 'idx_cliente_etiqueta_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_cliente_etiqueta_empresa');
        $this->forge->addForeignKey('id_cliente', 'cliente', 'id', 'CASCADE', 'CASCADE', 'fk_cliente_etiqueta_cliente');
        $this->forge->addForeignKey('id_etiqueta', 'etiqueta', 'id', 'CASCADE', 'CASCADE', 'fk_cliente_etiqueta_etiqueta');

        $this->forge->createTable('cliente_etiqueta');
    }

    public function down()
    {
        $this->forge->dropTable('cliente_etiqueta', true);
    }
}
