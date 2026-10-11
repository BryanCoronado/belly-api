<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAutomatizacionConversacionProgresoTable extends Migration
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
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'temperatura_actual' => [
                'type'       => 'ENUM',
                'constraint' => ['frio', 'tibio', 'caliente', 'venta'],
                'null'       => true,
            ],
            'ultimo_orden_enviado' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'ultimo_id_galeria_enviado' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'ultimo_mensaje_cliente_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ultima_ejecucion_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
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
        $this->forge->addForeignKey('id_conversacion', 'conversacion', 'id', 'CASCADE', 'CASCADE', 'fk_auto_prog_conv');
        $this->forge->addForeignKey('ultimo_id_galeria_enviado', 'galeria', 'id', 'SET NULL', 'SET NULL', 'fk_auto_prog_galeria');
        $this->forge->addUniqueKey('id_conversacion', 'uq_auto_conv_prog_conversacion');
        $this->forge->addKey(['temperatura_actual', 'activo'], false, false, 'idx_auto_conv_prog_temp_activo');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('automatizacion_conversacion_progreso');
    }

    public function down()
    {
        $this->forge->dropTable('automatizacion_conversacion_progreso', true);
    }
}
