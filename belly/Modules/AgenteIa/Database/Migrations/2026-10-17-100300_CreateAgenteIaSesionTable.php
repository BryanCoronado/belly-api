<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Conversaciones que atiende un agente de IA. `nodo_reparto` es el paso del lienzo de llegada
 * desde el que se sigue (salida «Pasa a una persona») cuando el agente deriva el chat.
 */
class CreateAgenteIaSesionTable extends Migration
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
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_agente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nodo_reparto' => [
                'type'       => 'VARCHAR',
                'constraint' => '40',
                'null'       => true,
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => '12',
                'default'    => 'activa',
            ],
            'respuestas' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'motivo_traspaso' => [
                'type'       => 'VARCHAR',
                'constraint' => '200',
                'null'       => true,
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
        $this->forge->addKey(['id_conversacion', 'estado'], false, false, 'idx_agente_ia_sesion_conversacion');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_agente_ia_sesion_empresa');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_agente_ia_sesion_conversacion');
        $this->forge->addForeignKey('id_agente', 'agente_ia', 'id', 'CASCADE', 'CASCADE', 'fk_agente_ia_sesion_agente');

        $this->forge->createTable('agente_ia_sesion');
    }

    public function down()
    {
        $this->forge->dropTable('agente_ia_sesion', true);
    }
}
