<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateTareaLlamadaHistorialTable extends Migration
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
            'id_estado_anterior' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_estado_nuevo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_motivo_tarea_llamada' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_llamada_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_usuario_accion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nota' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_tarea_llamada', 'tarea_llamada', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('id_motivo_tarea_llamada', 'motivo_tarea_llamada', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('id_llamada_conversacion', 'llamada_conversacion', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('id_usuario_accion', 'usuario', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addKey('id_tarea_llamada');

        $this->forge->createTable('tarea_llamada_historial');
    }

    public function down()
    {
        $this->forge->dropTable('tarea_llamada_historial');
    }
}
