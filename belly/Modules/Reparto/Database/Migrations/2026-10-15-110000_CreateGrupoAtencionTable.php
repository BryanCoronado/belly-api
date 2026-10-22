<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Grupos de asesores para repartir chats (por ejemplo «Turno mañana», «Ventas», «Soporte»). */
class CreateGrupoAtencionTable extends Migration
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
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => '200',
                'null'       => true,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => '7',
                'default'    => '#0d9488',
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'general',
            ],
            'turno_dias' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'turno_desde' => [
                'type'       => 'VARCHAR',
                'constraint' => '5',
                'null'       => true,
            ],
            'turno_hasta' => [
                'type'       => 'VARCHAR',
                'constraint' => '5',
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_empresa', 'deleted_at'], false, false, 'idx_grupo_atencion_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_grupo_atencion_empresa');

        $this->forge->createTable('grupo_atencion');
    }

    public function down()
    {
        $this->forge->dropTable('grupo_atencion', true);
    }
}
