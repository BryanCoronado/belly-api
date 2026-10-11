<?php

namespace Belly\Tareas\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateTareaMensajeProgramadoTable extends Migration
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
            'id_tarea_llamada' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'contexto' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'plantilla_nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'programado_at' => [
                'type' => 'DATETIME',
            ],
            'fecha_limite_snapshot' => [
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
        $this->forge->addForeignKey('id_tarea_llamada', 'tarea_llamada', 'id', 'CASCADE', 'CASCADE', 'fk_tarea_msg_prog_tarea');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_tarea_msg_prog_conv');
        $this->forge->addKey(['estado', 'programado_at'], false, false, 'idx_tarea_msg_prog_pendiente');
        $this->forge->addKey(['id_tarea_llamada', 'estado'], false, false, 'idx_tarea_msg_prog_tarea_estado');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('tarea_mensaje_programado');
    }

    public function down()
    {
        $this->forge->dropTable('tarea_mensaje_programado', true);
    }
}
