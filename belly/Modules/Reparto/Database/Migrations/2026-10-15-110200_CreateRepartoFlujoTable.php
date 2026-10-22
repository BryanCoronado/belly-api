<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Lienzo de llegada de cada empresa: qué pasa cuando un cliente inicia una conversación
 * (horarios, grupos, agente IA…). `definicion` es JSON {nodos, conexiones}. Una fila por empresa.
 */
class CreateRepartoFlujoTable extends Migration
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
            'definicion' => [
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('id_empresa', 'uq_reparto_flujo_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_reparto_flujo_empresa');

        $this->forge->createTable('reparto_flujo');
    }

    public function down()
    {
        $this->forge->dropTable('reparto_flujo', true);
    }
}
