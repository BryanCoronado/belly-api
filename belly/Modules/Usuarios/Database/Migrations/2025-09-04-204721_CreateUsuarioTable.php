<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioTable extends Migration
{
    public function up()
    {
        $this->createTableUsuario();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('usuario');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableUsuario(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_empresa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'usuario' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'codigo_recuperacion' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'fecha_recuperacion' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'login_fallidos' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'cuenta_bloqueada' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'fecha_bloqueo' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'id_estado_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'sudo' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'es_bot' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
            ],
            'forzar_cambio_pw' => [
                'type'       => 'TINYINT',
                'constraint' => '1',
                'default'    => 0,
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
        $this->forge->addKey('usuario');
        $this->forge->addUniqueKey(['id_empresa', 'usuario'], 'uk_usuario_empresa_usuario');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_usuario_empresa');
        $this->forge->addForeignKey('id_estado_usuario', 'estado_usuario', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('usuario');
    }
}
