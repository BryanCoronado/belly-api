<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Lo que el agente sabe: preguntas frecuentes, políticas, horarios, información del negocio… */
class CreateAgenteIaConocimientoTable extends Migration
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
            'id_agente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => '120',
            ],
            'contenido' => [
                'type' => 'LONGTEXT',
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
        $this->forge->addKey(['id_agente', 'deleted_at'], false, false, 'idx_agente_ia_conocimiento_agente');
        $this->forge->addForeignKey('id_agente', 'agente_ia', 'id', 'CASCADE', 'CASCADE', 'fk_agente_ia_conocimiento_agente');

        $this->forge->createTable('agente_ia_conocimiento');
    }

    public function down()
    {
        $this->forge->dropTable('agente_ia_conocimiento', true);
    }
}
