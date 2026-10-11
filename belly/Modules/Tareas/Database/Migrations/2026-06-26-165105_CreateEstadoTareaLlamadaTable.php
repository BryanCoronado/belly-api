<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateEstadoTareaLlamadaTable extends Migration
{
    public function up()
    {
        $this->createTableEstadoTareaLlamada();
        $this->seedEstados();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('estado_tarea_llamada');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableEstadoTareaLlamada(){
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
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('orden');
        $this->forge->createTable('estado_tarea_llamada');
    }

    private function seedEstados(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('estado_tarea_llamada')->insertBatch([
            [
                'id'          => 1,
                'nombre'      => 'Recontactar 1',
                'descripcion' => 'Primer intento de recontacto',
                'orden'       => 1,
                'created_at'  => $now,
            ],
            [
                'id'          => 2,
                'nombre'      => 'Recontactar 2',
                'descripcion' => 'Segundo intento de recontacto',
                'orden'       => 2,
                'created_at'  => $now,
            ],
            [
                'id'          => 3,
                'nombre'      => 'Recontactar 3',
                'descripcion' => 'Tercer intento de recontacto',
                'orden'       => 3,
                'created_at'  => $now,
            ],
            [
                'id'          => 4,
                'nombre'      => 'Completado',
                'descripcion' => 'Tarea finalizada',
                'orden'       => 6,
                'created_at'  => $now,
            ],
            [
                'id'          => 5,
                'nombre'      => 'Tareas adicionales',
                'descripcion' => 'Recontactos manuales tras completar el ciclo de 3 intentos',
                'orden'       => 5,
                'created_at'  => $now,
            ],
        ]);
    }
}
