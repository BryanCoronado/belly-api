<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/**
 * Una conexión de Meta por empresa: su app de Meta, el App Secret que firma los
 * eventos y el webhook propio (URL con clave única + token de verificación).
 */
class CreateConexionMetaTable extends Migration
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
            'meta_app_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'app_secret' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'webhook_clave' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
            ],
            'verify_token' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
            ],
            'webhook_verificado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'ultimo_evento_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addUniqueKey('id_empresa');
        $this->forge->addUniqueKey('webhook_clave');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_conexion_meta_empresa');

        $this->forge->createTable('conexion_meta');
    }

    public function down()
    {
        $this->forge->dropTable('conexion_meta', true);
    }
}
