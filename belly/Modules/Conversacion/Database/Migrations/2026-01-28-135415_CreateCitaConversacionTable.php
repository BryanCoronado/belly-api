<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateCitaConversacionTable extends Migration
{

    public function up()
    {
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
            'id_cita_erp' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'cita_estado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ],
            'fecha_agendacion' => [
                'type'       => 'DATETIME',
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
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('id_cita_erp');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('cita_conversacion');
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('cita_conversacion');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }
}
