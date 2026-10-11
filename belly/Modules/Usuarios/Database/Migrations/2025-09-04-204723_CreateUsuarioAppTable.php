<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioAppTable extends Migration
{
    public function up()
    {
        $this->createTableUsuarioApp();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('usuario_app');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableUsuarioApp(){
        $this->forge->addField([
            'id_usuario' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
            ],
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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

        // Clave primaria compuesta (sin ID)
        $this->forge->addPrimaryKey(['id_usuario', 'id_app']);
        
        // Foreign keys
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_app', 'app', 'id', 'CASCADE', 'CASCADE');

        
        // Agregar índice para soft deletes
        $this->forge->addKey('deleted_at');

        // Crear la tabla
        $this->forge->createTable('usuario_app');
    }
}
