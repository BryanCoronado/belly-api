<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGaleria extends Migration
{
    public function up()
    {
        $this->createTableGaleria();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('galeria');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGaleria()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_grupo_galeria' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'bloque' => [
                'type'       => 'ENUM',
                'constraint' => ['primero', 'segundo','tercero'],
                'null'       => true,
            ],
            'campaign_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'default'    => null,
                'comment'    => 'Meta campaign_id; NULL = general (sin campaña)',
            ],
            'filename' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'path' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => '1000',
            ],
            'original_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'size' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
            ],
            'tags' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'fecha_inicio' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'fecha_fin' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addForeignKey('id_grupo_galeria', 'grupo_galeria', 'id', 'CASCADE', 'CASCADE', '');
        $this->forge->addKey('id_grupo_galeria');
        $this->forge->addKey('tipo');
        $this->forge->addKey('campaign_id');
        $this->forge->addKey('created_at');
        $this->forge->addKey('deleted_at');

        $this->forge->createTable('galeria');
    }
}
