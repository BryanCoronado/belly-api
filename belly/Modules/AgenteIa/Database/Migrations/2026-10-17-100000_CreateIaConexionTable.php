<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Llave de OpenAI de cada empresa. Se guarda cifrada y nunca vuelve al navegador; solo se muestra la pista (últimos caracteres). */
class CreateIaConexionTable extends Migration
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
            'proveedor' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'openai',
            ],
            'api_key' => [
                'type' => 'TEXT',
            ],
            'pista' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
            ],
            'verificada_at' => [
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
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('id_empresa', 'uq_ia_conexion_empresa');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_ia_conexion_empresa');

        $this->forge->createTable('ia_conexion');
    }

    public function down()
    {
        $this->forge->dropTable('ia_conexion', true);
    }
}
