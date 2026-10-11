<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGrupoUsuarioUsuarioTable extends Migration
{
    public function up()
    {
        $this->createTableGrupoUsuarioUsuario();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('grupo_usuario_usuario');
        
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGrupoUsuarioUsuario()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_grupo_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_grupo_usuario_rol' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        $this->forge->addForeignKey('id_grupo_usuario', 'grupo_usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_grupo_usuario_rol', 'grupo_usuario_rol', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('grupo_usuario_usuario');
    }
}