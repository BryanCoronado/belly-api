<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateTipoDocumentoTable extends Migration
{
    public function up()
    {
        $this->createTableTipoDocumento();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('tipo_documento');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableTipoDocumento(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'longitud' => [
                'type' => 'TINYINT',
                'constraint' => '1',
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
        $this->forge->addKey('tipo');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('tipo_documento');
    }
}
