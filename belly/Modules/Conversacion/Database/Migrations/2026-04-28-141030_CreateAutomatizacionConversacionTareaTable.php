<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAutomatizacionConversacionTareaTable extends Migration
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
            'tipo_tarea' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'enviar_galeria',
            ],
            'bloque' => [
                'type'       => 'ENUM',
                'constraint' => ['primero', 'segundo', 'tercero'],
            ],
            'programado_at' => [
                'type' => 'DATETIME',
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'en_proceso', 'ejecutada', 'cancelada', 'error'],
                'default'    => 'pendiente',
            ],
            'intentos' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'payload' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'error' => [
                'type' => 'TEXT',
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
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_auto_tarea_conv');
        $this->forge->addKey(['estado', 'programado_at'], false, false, 'idx_auto_conv_tarea_pendiente');
        $this->forge->addKey(['id_conversacion', 'estado'], false, false, 'idx_auto_conv_tarea_conversacion_estado');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('automatizacion_conversacion_tarea');
    }

    public function down()
    {
        $this->forge->dropTable('automatizacion_conversacion_tarea', true);
    }
}
