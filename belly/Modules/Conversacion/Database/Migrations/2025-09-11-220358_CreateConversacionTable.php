<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateConversacionTable extends Migration
{
    public function up()
    {
        $this->createTableConversacion();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('conversacion');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableConversacion(){
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
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_asignado_por' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_estado' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'registro' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'id_motivo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_etiqueta' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'servicio' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'temperatura' => [
                'type' => 'ENUM',
                'constraint' => ['frio','tibio', 'caliente', 'venta'],
                'null' => true,
                'default' => 'frio',
            ],
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],

            "source_id" => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            "campaign_id" => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
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
            'asignado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_conversacion_empresa');
        $this->forge->addKey(['id_empresa', 'id_estado', 'deleted_at'], false, false, 'idx_conv_empresa_estado');
        $this->forge->addForeignKey('id_cliente','cliente','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_usuario','usuario','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_app','app','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_estado','estado_conversacion','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_motivo','motivo_conversacion','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_etiqueta','etiqueta','id','CASCADE','CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey(['id_usuario', 'id_estado', 'deleted_at'], false, false, 'idx_conv_usuario_estado');

        $this->forge->createTable('conversacion');
    }
}


