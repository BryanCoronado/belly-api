<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePlantillaRapidaTable extends Migration
{
    public function up()
    {
        $this->createTablePlantillaRapida();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('plantilla_rapida');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTablePlantillaRapida()
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
                'constraint' => '255',
            ],
            'contenido' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'imagen_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Ruta relativa en writable/downloads',
            ],
            'imagen_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'URL pública de la imagen',
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_plantilla_rapida_empresa');
        $this->forge->addForeignKey('id_usuario','usuario','id','CASCADE','CASCADE'); 

        $this->forge->addKey('deleted_at');

        $this->forge->createTable('plantilla_rapida');
    }
}