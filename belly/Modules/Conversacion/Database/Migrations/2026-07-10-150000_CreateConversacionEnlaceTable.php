<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateConversacionEnlaceTable extends Migration
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
            'canal_origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'comment'    => 'messenger, instagram, tiktok, etc.',
            ],
            'canal_destino' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'whatsapp',
            ],
            'id_conversacion_origen' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_conversacion_destino' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_cliente_origen' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_cliente_destino' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'telefono' => [
                'type'       => 'VARCHAR',
                'constraint' => 15,
            ],
            'contexto_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'vinculado',
            ],
            'ultimo_aviso_origen_at' => [
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('id_conversacion_origen');
        $this->forge->addUniqueKey('id_conversacion_destino');
        $this->forge->addKey(['canal_origen', 'canal_destino']);
        $this->forge->addForeignKey('id_conversacion_origen', 'conversacion', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_conversacion_destino', 'conversacion', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_cliente_origen', 'cliente', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_cliente_destino', 'cliente', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('telefono');

        $this->forge->createTable('conversacion_enlace');
    }

    public function down()
    {
        $this->forge->dropTable('conversacion_enlace');
    }
}
