<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateMensajeTable extends Migration
{
    public function up()
    {
        $this->createTableMensaje();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('mensaje');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableMensaje(){
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
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_estado' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'mensaje' => [
                'type'       => 'TEXT',
            ],
            'contesta' => [
                'type'       => 'ENUM',
                'constraint' => ['cliente','usuario','bot'],
            ],
            'id_mensaje_whatsapp' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'id_mensaje_responde' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'cod_error' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'media_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'media_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'media_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'payload' => [
                'type'       => 'TEXT',
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_mensaje_empresa');
        $this->forge->addForeignKey('id_conversacion','conversacion','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_estado','estado_mensaje','id','CASCADE','CASCADE'); 
        $this->forge->addKey('deleted_at');
        $this->forge->addKey(['id_conversacion', 'deleted_at', 'created_at'], false, false, 'idx_msg_conv_fecha');
        $this->forge->addKey(['id_conversacion', 'contesta', 'id_estado', 'deleted_at'], false, false, 'idx_msg_no_leido');
        // Idempotencia webhooks Meta (wamid / mid). Varios NULL permitidos en MySQL.
        $this->forge->addKey('id_mensaje_whatsapp', false, true, 'uq_mensaje_wamid');

        $this->forge->createTable('mensaje');
    }
}


