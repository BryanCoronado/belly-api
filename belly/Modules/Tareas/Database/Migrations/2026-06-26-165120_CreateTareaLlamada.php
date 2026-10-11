<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateTareaLlamadaTable extends Migration
{
    public function up()
    {
        $this->createTableTareaLlamada();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('tarea_llamada');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableTareaLlamada()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'contexto' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
                'null'       => true,
            ],
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_estado_tarea_llamada' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_motivo_tarea_llamada' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nivel_intento' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 1,
            ],
            'id_ultima_llamada' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'prioridad' => [
                'type'       => 'ENUM',
                'constraint' => ['baja', 'media', 'alta'],
                'default'    => 'media',
            ],
            'fecha_inicio' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'fecha_limite' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'activa' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 1,
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
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('id_estado_tarea_llamada', 'estado_tarea_llamada', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('id_motivo_tarea_llamada', 'motivo_tarea_llamada', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('id_estado_tarea_llamada');
        $this->forge->addKey('id_motivo_tarea_llamada');
        $this->forge->addKey('id_conversacion');
        $this->forge->addKey('id_usuario');
        $this->forge->addKey('activa');
        $this->forge->addKey('nivel_intento');

        $this->forge->createTable('tarea_llamada');
    }
}
