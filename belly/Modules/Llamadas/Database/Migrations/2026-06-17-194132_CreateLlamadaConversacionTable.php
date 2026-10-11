<?php

namespace Belly\Llamadas\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateLlamadaConversacionTable extends Migration
{
    public function up()
    {
        $this->createTableLlamadaConversacion();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('llamada_conversacion');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableLlamadaConversacion(){
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'call_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'id_conversacion' => [
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
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'direccion' => [
                'type'       => 'ENUM',
                'constraint' => ['entrante', 'saliente'],
                'default'    => 'saliente',
            ],
            'resultado' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'colgado_por' => [
                'type'       => 'ENUM',
                'constraint' => ['cliente', 'asesor', 'sistema', 'desconocido'],
                'null'       => true,
            ],
            'resultado_detalle' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'sip_hangup_cause' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'sip_hangup_code' => [
                'type'       => 'SMALLINT',
                'constraint' => 6,
                'null'       => true,
            ],
            'transferida' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'extension_transferencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'cdr_import',
            ],
            'pbx_server' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'duracion' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'fecha_inicio' => [
                'type'       => 'DATETIME',
            ],
            'fecha_fin' => [
                'type'       => 'DATETIME',
                'null'       => true,
            ],
            'cdr_record_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'telefono_contacto' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
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
        $this->forge->addForeignKey('id_conversacion','conversacion','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_usuario','usuario','id','CASCADE','CASCADE');
        $this->forge->addForeignKey('id_cliente','cliente','id','CASCADE','CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addUniqueKey('call_id', 'llamada_conversacion_call_id');
        $this->forge->addUniqueKey('cdr_record_name', 'llamada_conversacion_cdr_record_name');
        $this->forge->addUniqueKey(['extension', 'fecha_inicio', 'duracion', 'telefono_contacto'], 'llamada_conversacion_dedupe_tiempo');

        $this->forge->createTable('llamada_conversacion');
    }
}
