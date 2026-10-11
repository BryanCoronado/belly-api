<?php

namespace Belly\Clientes\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateListaNegra extends Migration
{
    public function up()
    {
        $this->createTableListaNegra();

    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('lista_negra');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }


    private function createTableListaNegra(){
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
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => '15',
            ],
            'motivo' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_lista_negra_empresa');
        $this->forge->addUniqueKey(['id_empresa', 'telefono'], 'uk_lista_negra_empresa_telefono');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('lista_negra');
    }
}
