<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePlantillaMetaTable extends Migration
{
    public function up()
    {
        $this->createTablePlantillaMeta();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->forge->dropTable('plantilla_meta');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTablePlantillaMeta()
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
            'template_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'id_estado' => [
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
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_plantilla_meta_empresa');
        $this->forge->addUniqueKey(['id_empresa', 'template_id'], 'uk_plantilla_meta_empresa_template');
        $this->forge->addForeignKey('id_estado','estado_plantilla_meta','id','CASCADE','CASCADE'); 
        $this->forge->addForeignKey('id_app','app','id','CASCADE','CASCADE');

        $this->forge->addKey('deleted_at');

        $this->forge->createTable('plantilla_meta');
    }
}