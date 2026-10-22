<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Etapas del embudo de ventas que cada empresa define para ordenar a sus clientes. */
class CreateEmbudoEtapaTable extends Migration
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
                'constraint' => 60,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => '#64748b',
            ],
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'resultado' => [
                'type'       => 'ENUM',
                'constraint' => ['abierta', 'ganada', 'perdida'],
                'default'    => 'abierta',
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
        $this->forge->addKey(['id_empresa', 'deleted_at', 'orden'], false, false, 'idx_embudo_etapa_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_embudo_etapa_empresa');

        $this->forge->createTable('embudo_etapa');
    }

    public function down()
    {
        $this->forge->dropTable('embudo_etapa', true);
    }
}
