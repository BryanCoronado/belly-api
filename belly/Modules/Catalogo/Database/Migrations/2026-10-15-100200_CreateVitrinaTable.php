<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Vitrina pública de cada empresa: el catálogo virtual con su propio enlace.
 */
class CreateVitrinaTable extends Migration
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
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => '60',
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => '80',
            ],
            'eslogan' => [
                'type'       => 'VARCHAR',
                'constraint' => '160',
                'null'       => true,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => '7',
                'default'    => '#0B1026',
            ],
            'logo_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'whatsapp' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
            ],
            'mostrar_precios' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'publicado' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'pagina' => [
                'type' => 'LONGTEXT',
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
        $this->forge->addUniqueKey('id_empresa', 'uk_vitrina_empresa');
        $this->forge->addUniqueKey('slug', 'uk_vitrina_slug');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_vitrina_empresa');
        $this->forge->createTable('vitrina');
    }

    public function down()
    {
        $this->forge->dropTable('vitrina', true);
    }
}
