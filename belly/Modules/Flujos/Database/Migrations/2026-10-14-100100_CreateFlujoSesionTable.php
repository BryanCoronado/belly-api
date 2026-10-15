<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * En qué paso va cada conversación dentro de un flujo y qué datos le ha ido dando el cliente.
 * Una conversación puede tener varias sesiones a lo largo del tiempo, pero solo una activa.
 */
class CreateFlujoSesionTable extends Migration
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
            'id_flujo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['activa', 'esperando', 'terminada', 'derivada'],
                'default'    => 'activa',
            ],
            'nodo_actual' => [
                'type'       => 'VARCHAR',
                'constraint' => '40',
                'null'       => true,
            ],
            'intentos' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'variables' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // Cuándo debe retomarse solo (paso «Esperar» o cliente que no respondió a tiempo).
            'reanudar_at' => [
                'type' => 'DATETIME',
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_conversacion', 'estado'], false, false, 'idx_flujo_sesion_conversacion');
        $this->forge->addKey(['id_flujo', 'estado'], false, false, 'idx_flujo_sesion_flujo');
        $this->forge->addKey(['reanudar_at'], false, false, 'idx_flujo_sesion_reanudar');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_flujo_sesion_empresa');
        $this->forge->addForeignKey('id_flujo', 'flujo', 'id', 'CASCADE', 'CASCADE', 'fk_flujo_sesion_flujo');
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_flujo_sesion_conversacion');

        $this->forge->createTable('flujo_sesion');
    }

    public function down()
    {
        $this->forge->dropTable('flujo_sesion', true);
    }
}
