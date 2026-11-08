<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Pedidos que llegan desde la tienda web (carrito → WhatsApp). */
class CreatePedidoCatalogoTable extends Migration
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
            'id_cliente' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'tienda' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
                'null'       => true,
            ],
            'cliente_nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => '120',
                'null'       => true,
            ],
            'nota' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'items' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'total' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'moneda' => [
                'type'       => 'VARCHAR',
                'constraint' => '3',
                'default'    => 'PEN',
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'nuevo',
            ],
            'texto' => [
                'type' => 'TEXT',
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_empresa', 'estado', 'created_at'], false, false, 'idx_pedido_catalogo_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_pedido_catalogo_empresa');
        $this->forge->createTable('pedido_catalogo');
    }

    public function down()
    {
        $this->forge->dropTable('pedido_catalogo', true);
    }
}
