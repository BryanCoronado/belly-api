<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGrupoPermisoTable extends Migration
{
    public function up()
    {
        $this->createTableGrupoPermiso();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('grupo_permiso');
        
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGrupoPermiso()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'grupo' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
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
        $this->forge->createTable('grupo_permiso');
    }
}