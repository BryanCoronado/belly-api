<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateConversacionVentaTable extends Migration
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
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'Asesor dueño de la conversación al momento de la venta',
            ],
            'id_usuario_registro' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Usuario que marcó la venta',
            ],
            'temperatura_anterior' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'registrada_at' => [
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
        $this->forge->addUniqueKey('id_conversacion', 'uq_conversacion_venta_conversacion');
        $this->forge->addKey('id_cliente');
        $this->forge->addKey('id_usuario');
        $this->forge->addKey('registrada_at');
        $this->forge->addKey('deleted_at');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_cliente', 'cliente', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('conversacion_venta');
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('conversacion_venta');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }
}
