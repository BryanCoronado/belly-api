<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioPermisoEspecialTable extends Migration
{
    public function up()
    {
        $this->createTableUsuarioPermisoEspecial();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('usuario_permiso_especial');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableUsuarioPermisoEspecial()
    {
        $this->forge->addField([
            'id_usuario' => [
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
                'constraint' => '1',
                'default'    => 0,
            ],
            'acc_lectura' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'acc_actualizar' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'acc_eliminar' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 1,
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
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 1,
            ],
        ]);
        $this->forge->addPrimaryKey(['id_usuario', 'id_permiso']);
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_permiso', 'permiso', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('usuario_permiso_especial');
    }
}