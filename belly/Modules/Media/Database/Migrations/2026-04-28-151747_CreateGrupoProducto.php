<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateGrupoProducto extends Migration
{
    public function up()
    {
        $this->createTableGrupoProducto();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('grupo_producto');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableGrupoProducto()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => '160',
                'null'       => true,
            ],
            'descripcion_web' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'mostrar_web' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'seo_title' => [
                'type'       => 'VARCHAR',
                'constraint' => '160',
                'null'       => true,
            ],
            'seo_description' => [
                'type'       => 'VARCHAR',
                'constraint' => '320',
                'null'       => true,
            ],
            'mensaje_whatsapp' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Mensaje Cotizar de la familia. Placeholders: {nombre} {familia} {url}',
            ],
            'sinonimos' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'subcategorias' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'prioridad' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'filename' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'path' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => '1000',
                'null'       => true,
            ],
            'original_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'size' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
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
        $this->forge->addKey('slug');
        $this->forge->addKey('mostrar_web');
        $this->forge->addKey('color');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('grupo_producto');
    }
}
