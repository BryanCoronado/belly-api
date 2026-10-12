<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioPerfilTable extends Migration
{
    public function up()
    {
        $this->createTableUsuarioPerfil();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('usuario_perfil');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableUsuarioPerfil(){
        $this->forge->addField([
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unique'     => true,
                'unsigned'   => true,
            ],
            'nombres' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'apellidos' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
                'null'       => true,
            ],
            'id_tipo_documento' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nro_documento' => [
                'type'       => 'VARCHAR',
                'constraint' => '15',
                'null'       => true,
            ],
            'correo' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'celular' => [
                'type'       => 'VARCHAR',
                'constraint' => '15',
                'null'       => true,
            ],
            'celular_secundario' => [
                'type'       => 'VARCHAR',
                'constraint' => '15',
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

        $this->forge->addPrimaryKey('id_usuario');
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_tipo_documento', 'tipo_documento', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('usuario_perfil');

       
    }
}
