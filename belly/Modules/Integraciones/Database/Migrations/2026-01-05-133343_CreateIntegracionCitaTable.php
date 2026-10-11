<?php

namespace Belly\Integraciones\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateIntegracionCitaTable extends Migration
{
    public function up()
    {
        $this->createTableIntegracionCita();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('integracion_cita');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableIntegracionCita(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            //usuario de otro sistema
            'id_usuario_cita' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre_usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
        $this->forge->addForeignKey('id_usuario','usuario','id','CASCADE','CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('nombre_usuario');
        $this->forge->addKey('id_usuario_cita');

        $this->forge->createTable('integracion_cita');
    }
}


