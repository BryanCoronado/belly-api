<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateComentarioConversacionTable extends Migration
{
    public function up()
    {
        $this->createTableComentarioConversacion();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('comentario_conversacion');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableComentarioConversacion(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'comentario' => [
                'type' => 'TEXT',
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
        $this->forge->addForeignKey('id_conversacion','conversacion','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_usuario','usuario','id','CASCADE','CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('id_conversacion');

        $this->forge->createTable('comentario_conversacion');
    }
}

