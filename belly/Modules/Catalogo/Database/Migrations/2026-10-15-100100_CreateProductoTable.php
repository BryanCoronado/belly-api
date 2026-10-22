<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Productos o servicios de una empresa. Sirve para cualquier negocio:
 * lo común va en columnas y lo propio de cada rubro en `detalles` (lista de nombre y valor).
 */
class CreateProductoTable extends Migration
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
            'id_familia' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
            ],
            'descripcion' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'codigo' => [
                'type'       => 'VARCHAR',
                'constraint' => '60',
                'null'       => true,
            ],
            'precio' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'moneda' => [
                'type'       => 'VARCHAR',
                'constraint' => '3',
                'default'    => 'PEN',
            ],
            'unidad' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'null'       => true,
            ],
            'imagen_url' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
            ],
            'detalles' => [
                'type' => 'LONGTEXT',
                'null' => true,
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
        $this->forge->addKey(['id_empresa', 'id_familia', 'activo', 'deleted_at'], false, false, 'idx_producto_empresa_familia');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_producto_empresa');
        $this->forge->addForeignKey('id_familia', 'familia_producto', 'id', 'SET NULL', 'CASCADE', 'fk_producto_familia');

        $this->forge->createTable('producto');
    }

    public function down()
    {
        $this->forge->dropTable('producto', true);
    }
}
