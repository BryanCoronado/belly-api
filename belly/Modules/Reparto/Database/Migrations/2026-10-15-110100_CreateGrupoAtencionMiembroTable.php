<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Asesores de cada grupo. Una persona puede estar en varios grupos; `orden` sirve para el reparto por prioridad. */
class CreateGrupoAtencionMiembroTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_grupo' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addPrimaryKey(['id_grupo', 'id_usuario']);
        $this->forge->addKey('id_usuario', false, false, 'idx_grupo_atencion_miembro_usuario');
        $this->forge->addForeignKey('id_grupo', 'grupo_atencion', 'id', 'CASCADE', 'CASCADE', 'fk_grupo_atencion_miembro_grupo');
        $this->forge->addForeignKey('id_usuario', 'usuario', 'id', 'CASCADE', 'CASCADE', 'fk_grupo_atencion_miembro_usuario');

        $this->forge->createTable('grupo_atencion_miembro');
    }

    public function down()
    {
        $this->forge->dropTable('grupo_atencion_miembro', true);
    }
}
