<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGrupoUsuarioPermisoTable extends Migration
{
    public function up()
    {
        $this->createTableGrupoUsuarioPermiso();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('grupo_usuario_permiso');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGrupoUsuarioPermiso()
    {
        $this->forge->addField([
            'id_grupo_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_permiso' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'acc_crear' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'acc_lectura' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'acc_actualizar' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'acc_eliminar' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
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

        // Agregar clave primaria compuesta
        $this->forge->addKey(['id_grupo_usuario', 'id_permiso'], true);

        // Agregar claves foráneas
        $this->forge->addForeignKey('id_grupo_usuario', 'grupo_usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_permiso', 'permiso', 'id', 'CASCADE', 'CASCADE');

        // Agregar índice para soft deletes
        $this->forge->addKey('deleted_at');

        // Crear la tabla
        $this->forge->createTable('grupo_usuario_permiso');
    }
}