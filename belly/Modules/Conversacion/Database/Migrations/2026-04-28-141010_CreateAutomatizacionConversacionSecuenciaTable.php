<?php

namespace Belly\Conversacion\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAutomatizacionConversacionSecuenciaTable extends Migration
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
            'temperatura' => [
                'type'       => 'ENUM',
                'constraint' => ['frio', 'tibio', 'caliente'],
            ],
            'bloque' => [
                'type'       => 'ENUM',
                'constraint' => ['primero', 'segundo', 'tercero'],
            ],
            'id_grupo_galeria' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_galeria' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        $this->forge->addForeignKey('id_grupo_galeria', 'grupo_galeria', 'id', 'CASCADE', 'SET NULL', 'fk_auto_sec_grupo');
        $this->forge->addForeignKey('id_galeria', 'galeria', 'id', 'CASCADE', 'CASCADE', 'fk_auto_sec_galeria');
        $this->forge->addUniqueKey(['temperatura', 'bloque', 'orden'], 'uq_auto_conv_sec_temp_bloque_orden');
        $this->forge->addKey(['temperatura', 'bloque', 'activo'], false, false, 'idx_auto_conv_sec_busqueda');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('automatizacion_conversacion_secuencia');
    }

    public function down()
    {
        $this->forge->dropTable('automatizacion_conversacion_secuencia', true);
    }
}
