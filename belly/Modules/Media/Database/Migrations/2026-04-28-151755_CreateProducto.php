<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateProducto extends Migration
{
    public function up()
    {
        $this->createTableProducto();
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0;');

        $this->forge->dropTable('producto');

        $this->db->query('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createTableProducto()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_grupo_producto' => [
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
                'constraint' => '500',
                'null'       => true,
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
            'tipo_calculo' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'prioridad' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'sinonimos' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'especificaciones_tecnicas' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'especificaciones_web' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'caracteristicas_web' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'subcategoria' => [
                'type'       => 'VARCHAR',
                'constraint' => '120',
                'null'       => true,
            ],
            'subcategoria_orden' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'oferta' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'oferta_texto' => [
                'type'       => 'VARCHAR',
                'constraint' => '80',
                'null'       => true,
            ],
            'mensaje_whatsapp' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Mensaje del boton Cotizar. Placeholders: {nombre} {familia} {url}',
            ],
            'colores' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'atributos' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'relacionados' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
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
                'type'     => 'BIGINT',
                'unsigned' => true,
            ],
            'ficha_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'ficha_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'ficha_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '1000',
                'null'       => true,
            ],
            'ficha_mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'ficha_size' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
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
        $this->forge->addForeignKey('id_grupo_producto', 'grupo_producto', 'id', 'CASCADE', 'CASCADE', '');
        $this->forge->addKey('id_grupo_producto');
        $this->forge->addKey('slug');
        $this->forge->addKey('mostrar_web');
        $this->forge->addKey('subcategoria');
        $this->forge->addKey('color');
        $this->forge->addKey('created_at');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('producto');
    }
}
