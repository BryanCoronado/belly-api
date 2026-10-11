<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateMotivoTareaLlamadaTable extends Migration
{
    public function up()
    {
        $this->createTableMotivoTareaLlamada();
        $this->seedMotivos();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('motivo_tarea_llamada');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableMotivoTareaLlamada()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'descripcion' => [
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
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('motivo_tarea_llamada');
    }

    private function seedMotivos(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('motivo_tarea_llamada')->insertBatch([
            [
                'id'          => 1,
                'nombre'      => 'Automático',
                'descripcion' => 'Movimiento automático por llamada o etiqueta',
                'created_at'  => $now,
            ],
            [
                'id'          => 2,
                'nombre'      => 'Recontactar por fecha',
                'descripcion' => 'Llamada programada con el cliente',
                'created_at'  => $now,
            ],
            [
                'id'          => 3,
                'nombre'      => 'Venta / interesado',
                'descripcion' => 'Cliente interesado; continúa por WhatsApp',
                'created_at'  => $now,
            ],
            [
                'id'          => 4,
                'nombre'      => 'Cierre manual',
                'descripcion' => 'Cierre desde tablero o no interesado',
                'created_at'  => $now,
            ],
            [
                'id'          => 5,
                'nombre'      => 'Recontacto manual',
                'descripcion' => 'Tarea adicional creada por la asesora',
                'created_at'  => $now,
            ],
            [
                'id'          => 6,
                'nombre'      => 'Posible venta',
                'descripcion' => 'Interesado; continúa coordinación por WhatsApp en seguimiento',
                'created_at'  => $now,
            ],
            [
                'id'          => 7,
                'nombre'      => 'No interesado',
                'descripcion' => 'Cliente no interesado; conversación finalizada',
                'created_at'  => $now,
            ],
        ]);
    }
}
