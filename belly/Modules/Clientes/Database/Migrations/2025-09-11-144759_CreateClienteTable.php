<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateClienteTable extends Migration
{
    public function up()
    {
        $this->createTableCliente();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('cliente');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableCliente(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => '15',
                'null'       => true,
                'unique'     => true,
            ],
            'psid' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
                'unique'     => true,
            ],
            'wa_user_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '128',
                'null'       => true,
                'unique'     => true,
                'comment'    => 'Business-scoped user ID WhatsApp (US.…)',
            ],
            'wa_parent_user_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '128',
                'null'       => true,
                'comment'    => 'Parent BSUID WhatsApp (opcional)',
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
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('wa_parent_user_id');

        $this->forge->createTable('cliente');
    }
}


