<?php

namespace Belly\Usuarios\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateUsuarioHorarioBot extends Migration
{
    public function up()
    {
        $this->createTableUsuarioHorarioBot();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('horario_grupo_usuario_rol');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableUsuarioHorarioBot()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_grupo_usuario_rol' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'dia_semana' => [
                'type'       => 'ENUM',
                'constraint' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
            ],
            'hora_inicio' => [
                'type'       => 'TIME',
                'null'       => false,
            ],
            'hora_fin' => [
                'type'       => 'TIME',
                'null'       => false,
            ],
            'activo' => [
                'type'       => 'BOOLEAN',
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => new RawSql('NULL ON UPDATE CURRENT_TIMESTAMP'),
            ],
            'deleted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('id_grupo_usuario_rol', 'grupo_usuario_rol', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey('deleted_at');
        $this->forge->addKey('activo');

        $this->forge->createTable('horario_grupo_usuario_rol', true, ['ENGINE' => 'InnoDB']);
    }
}