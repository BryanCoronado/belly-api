<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAppTable extends Migration
{
    public function up()
    {
        $this->createTableApp();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('app');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableApp(){
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
            'id_portafolio' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
            ],
            'phone_number_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'canal' => [
                'type'       => 'ENUM',
                'constraint' => ['whatsapp'],
                'default'    => 'whatsapp',
            ],
            'whatsapp_business_account_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'access_token' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'verificado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'estado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'tipo' => [
                'type'       => 'ENUM',
                'constraint' => ['BOT', 'NORMAL'],
                'default'    => 'NORMAL',
            ],
            'modo' => [
                'type' => 'ENUM',
                'constraint' => ['individual', 'compartido'],
                'default' => 'individual',
            ],
            'max_usuarios' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 1,
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
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_app_empresa');
        $this->forge->addForeignKey('id_portafolio','portafolio','id','CASCADE','CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addUniqueKey('phone_number_id');

        $this->forge->createTable('app');
    }
}


