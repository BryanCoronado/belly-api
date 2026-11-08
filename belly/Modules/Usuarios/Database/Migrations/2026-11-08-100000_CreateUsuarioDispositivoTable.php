<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioDispositivoTable extends Migration
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
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_empresa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'expo_push_token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'plataforma' => [
                'type'       => 'ENUM',
                'constraint' => ['ios', 'android'],
                'default'    => 'android',
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'id_conversacion_abierta' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Chat abierto en primer plano; evita push duplicado',
            ],
            'visto_en' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addUniqueKey('expo_push_token');
        $this->forge->addKey(['id_usuario', 'activo']);
        $this->forge->addKey('id_empresa');
        $this->forge->createTable('usuario_dispositivo', true);
    }

    public function down()
    {
        $this->forge->dropTable('usuario_dispositivo', true);
    }
}
