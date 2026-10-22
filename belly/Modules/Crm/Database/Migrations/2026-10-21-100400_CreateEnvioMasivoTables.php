<?php

namespace Belly\Crm\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

/** Envíos masivos de WhatsApp y la lista de destinatarios de cada uno. */
class CreateEnvioMasivoTables extends Migration
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
            'id_app' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'id_usuario' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'modo' => [
                'type'       => 'ENUM',
                'constraint' => ['texto', 'plantilla'],
            ],
            'mensaje' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'plantilla' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'filtros' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['enviando', 'terminado', 'cancelado'],
                'default'    => 'enviando',
            ],
            'total' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'terminado_at' => [
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
        $this->forge->addKey(['id_empresa', 'deleted_at', 'created_at'], false, false, 'idx_envio_masivo_empresa');
        $this->forge->addKey(['estado'], false, false, 'idx_envio_masivo_estado');
        $this->forge->addForeignKey('id_empresa', 'empresa', 'id', 'CASCADE', 'CASCADE', 'fk_envio_masivo_empresa');
        $this->forge->createTable('envio_masivo');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'id_envio' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
            ],
            'id_conversacion' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'estado' => [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'enviando', 'enviado', 'fallido', 'omitido'],
                'default'    => 'pendiente',
            ],
            'detalle' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'enviado_at' => [
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
        $this->forge->addUniqueKey(['id_envio', 'id_cliente'], 'uk_envio_destinatario');
        $this->forge->addKey(['id_envio', 'estado'], false, false, 'idx_envio_destinatario_estado');
        $this->forge->addForeignKey('id_envio', 'envio_masivo', 'id', 'CASCADE', 'CASCADE', 'fk_envio_destinatario_envio');
        $this->forge->addForeignKey('id_cliente', 'cliente', 'id', 'CASCADE', 'CASCADE', 'fk_envio_destinatario_cliente');
        $this->forge->createTable('envio_masivo_destinatario');
    }

    public function down()
    {
        $this->forge->dropTable('envio_masivo_destinatario', true);
        $this->forge->dropTable('envio_masivo', true);
    }
}
