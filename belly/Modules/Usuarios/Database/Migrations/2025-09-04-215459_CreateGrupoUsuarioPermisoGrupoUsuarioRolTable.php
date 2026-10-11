<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGrupoUsuarioPermisoGrupoUsuarioRolTable extends Migration
{
    public function up()
    {
        $this->createTableGrupoUsuarioPermisoGrupoUsuarioRol();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('grupo_usuario_permiso_rol');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGrupoUsuarioPermisoGrupoUsuarioRol()
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
            'id_grupo_usuario_rol' => [
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

        $this->forge->addKey(['id_grupo_usuario', 'id_permiso', 'id_grupo_usuario_rol'], true);
        $this->forge->addForeignKey('id_grupo_usuario', 'grupo_usuario', 'id', 'CASCADE', 'CASCADE', '');
        $this->forge->addForeignKey('id_permiso', 'permiso', 'id', 'CASCADE', 'CASCADE', 'fk_gup_p');
        $this->forge->addForeignKey('id_grupo_usuario_rol', 'grupo_usuario_rol', 'id', 'CASCADE', 'CASCADE', 'fk_gup_gur');
       
        
        $this->forge->addKey('deleted_at');
        
        $this->forge->createTable('grupo_usuario_permiso_rol');
    }
}