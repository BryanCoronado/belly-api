<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Agentes de IA que atienden por WhatsApp: cómo hablan, qué saben y cuándo pasan el chat a una persona. */
class CreateAgenteIaTable extends Migration
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
            'id_empresa' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '60',
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'modelo' => [
                'type'       => 'VARCHAR',
                'constraint' => '40',
                'default'    => 'gpt-4o-mini',
            ],
            'instrucciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tono' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'amable',
            ],
            'usar_catalogo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'max_respuestas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 15,
            ],
            'palabras_traspaso' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'mensaje_traspaso' => [
                'type'       => 'VARCHAR',
                'constraint' => '300',
                'null'       => true,
            ],
            'temperatura' => [
                'type'       => 'DECIMAL',
                'constraint' => '3,2',
                'default'    => '0.40',
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
        $this->forge->addKey(['id_empresa', 'deleted_at'], false, false, 'idx_agente_ia_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_agente_ia_empresa');

        $this->forge->createTable('agente_ia');
    }

    public function down()
    {
        $this->forge->dropTable('agente_ia', true);
    }
}
